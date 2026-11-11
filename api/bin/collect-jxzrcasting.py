"""Collect the company's current public catalogue into a reviewed import snapshot.

Run locally with: python bin/collect-jxzrcasting.py
Requires requests and beautifulsoup4. The generated snapshot and images are
committed so production import does not depend on the source website being up.
"""

from __future__ import annotations

import concurrent.futures
import hashlib
import json
import re
import sys
import threading
import time
from pathlib import Path
from urllib.parse import urljoin, urlparse
from xml.etree import ElementTree

import requests
from bs4 import BeautifulSoup


ORIGIN = "https://www.jxzrcasting.com"
ROOT = Path(__file__).resolve().parents[1]
OUTPUT = ROOT / "database" / "jxzrcasting"
ASSETS = OUTPUT / "assets"
CACHE = ROOT / "runtime" / "jxzrcasting-cache"
HEADERS = {"User-Agent": "Mozilla/5.0 (compatible; ZhirenMiniContentSync/1.0)"}
LOCAL = threading.local()
REFRESH = "--refresh" in sys.argv


def fetch(url: str) -> bytes:
    cached = not re.search(r"\.(?:jpe?g|png|webp)(?:\?|$)", url, re.I)
    cache_path = CACHE / (hashlib.sha256(url.encode()).hexdigest() + ".bin")
    if cached and not REFRESH and cache_path.is_file():
        return cache_path.read_bytes()
    if not hasattr(LOCAL, "session"):
        LOCAL.session = requests.Session()
        LOCAL.session.headers.update(HEADERS)
    last_error = None
    for attempt in range(5):
        try:
            response = LOCAL.session.get(url, timeout=(12, 30))
            response.raise_for_status()
            data = response.content
            if cached:
                CACHE.mkdir(parents=True, exist_ok=True)
                cache_path.write_bytes(data)
            return data
        except requests.RequestException as error:
            last_error = error
            time.sleep(min(2 ** attempt, 8))
    raise RuntimeError(f"Fetch failed after retries: {url}: {last_error}")


def soup(url: str) -> BeautifulSoup:
    return BeautifulSoup(fetch(url), "html.parser")


def clean(element) -> str:
    return re.sub(r"\s+", " ", element.get_text(" ", strip=True)).strip() if element else ""


def sitemap(kind: str) -> list[str]:
    root = ElementTree.fromstring(fetch(f"{ORIGIN}/wp-sitemap-content-{kind}-1.xml"))
    return [node.text for node in root.iter() if node.tag.endswith("loc") and node.text]


def image_url(element) -> str:
    image = (element if element.name == "img" else element.select_one("img")) if element else None
    if not image:
        return ""
    value = image.get("src", "")
    return urljoin(ORIGIN, value) if value and not value.startswith("data:") else ""


def detail(item: dict) -> dict:
    page = soup(item["source_url"])
    main = page.select_one("main")
    if not main or not main.select_one(".zr-rfq-spec"):
        raise RuntimeError(f"Missing product detail: {item['source_url']}")
    requirements = []
    for part in main.select(".zr-rfq-spec dl > div"):
        key, value = clean(part.select_one("dt")), clean(part.select_one("dd"))
        if key and value:
            requirements.append(f"{key}：{value}")
    materials = []
    for element in main.select(".zr-material-options .zr-material-option"):
        value = clean(element)
        if value and value not in materials:
            materials.append(value)
    application = clean(main.select_one(".zr-related-apps"))
    application = re.sub(r"^查看适用场景\s*", "", application).replace("↗", "").strip()
    return {**item, "requirements": requirements, "materials": materials[:8], "application": application[:800]}


def article(url: str) -> dict:
    page = soup(url)
    main = page.select_one("main")
    story = (main.select_one("article.prose") or main.select_one("article")) if main else None
    if not story:
        raise RuntimeError(f"Missing article body: {url}")
    meta = page.select_one('meta[property="article:published_time"]')
    date = (meta.get("content", "") if meta else "")[:10]
    if not date:
        raise RuntimeError(f"Missing article date: {url}")
    paragraphs = [clean(p) for p in story.select("p") if clean(p)]
    cover = page.select_one('meta[property="og:image"]')
    cover_url = urljoin(ORIGIN, cover.get("content", "")) if cover and cover.get("content") else image_url(story)
    return {"slug": urlparse(url).path.strip("/").split("/")[-1], "source_url": url,
            "title_en": clean(main.select_one("h1")), "published_at": date + " 00:00:00",
            "intro_en": paragraphs[:3], "image_url": cover_url}


def asset_record(url: str) -> tuple[str, dict]:
    suffix = Path(urlparse(url).path).suffix.lower()
    if suffix not in {".jpg", ".jpeg", ".png", ".webp"}:
        raise RuntimeError(f"Unexpected image type: {url}")
    filename = hashlib.sha256(url.encode()).hexdigest()[:20] + suffix
    ASSETS.mkdir(parents=True, exist_ok=True)
    path = ASSETS / filename
    if path.is_file() and not REFRESH:
        data = path.read_bytes()
    else:
        data = fetch(url)
        path.write_bytes(data)
    if not data or len(data) > 5_000_000:
        raise RuntimeError(f"Unexpected image size: {url}")
    return url, {"file": filename, "sha256": hashlib.sha256(data).hexdigest(),
                 "bytes": len(data), "relative_url": "/uploads/jxzrcasting/" + filename}


def main() -> None:
    index = soup(f"{ORIGIN}/products/?lang=zh-cn")
    categories = []
    for anchor in index.select('main a[href^="/products/"]'):
        path = urlparse(anchor.get("href", "")).path
        if path.count("/") != 3:
            continue
        slug = path.strip("/").split("/")[-1]
        title = clean(anchor.select_one("h2"))
        if not title or any(entry["slug"] == slug for entry in categories):
            continue
        categories.append({"slug": slug, "title": title, "description": clean(anchor.select_one("p")),
                           "image_url": image_url(anchor), "source_url": ORIGIN + path})
    if len(categories) != 8:
        raise RuntimeError(f"Expected 8 catalogue families, found {len(categories)}")
    print("Catalogue families: 8", flush=True)

    def category_products(category: dict) -> list[dict]:
        page = soup(category["source_url"] + "?lang=zh-cn")
        result = []
        for anchor in page.select("a.zr-casting-card"):
            path = urlparse(anchor.get("href", "")).path
            slug = path.strip("/").split("/")[-1]
            title = clean(anchor.select_one("h2"))
            if not slug or not title:
                continue
            result.append({"slug": slug, "category_slug": category["slug"], "title": title,
                           "summary": clean(anchor.select_one("p")), "image_url": image_url(anchor),
                           "source_url": ORIGIN + path + "?lang=zh-cn"})
        if not result:
            raise RuntimeError(f"No products in {category['slug']}")
        return result

    with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool:
        groups = list(pool.map(category_products, categories))
    products = {}
    for group in groups:
        for item in group:
            products.setdefault(item["slug"], item)
    if len(products) < 90:
        raise RuntimeError(f"Catalogue unexpectedly small: {len(products)}")
    print(f"Product cards: {len(products)}; loading detail pages", flush=True)
    with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool:
        product_rows = list(pool.map(detail, products.values()))
    print(f"Product details: {len(product_rows)}", flush=True)

    article_urls = sitemap("posts")
    with concurrent.futures.ThreadPoolExecutor(max_workers=3) as pool:
        articles = list(pool.map(article, article_urls))
    print(f"Technical guides: {len(articles)}", flush=True)
    home = soup(ORIGIN + "/?lang=zh-cn")
    home_main = home.select_one("main") or home
    about = soup(ORIGIN + "/about/?lang=zh-cn")
    capabilities = soup(ORIGIN + "/capabilities/?lang=zh-cn")
    solutions = soup(ORIGIN + "/solutions/?lang=zh-cn")
    contact = soup(ORIGIN + "/contact/?lang=zh-cn")
    logo = home.select_one("header img")
    source_pages = {"about": about, "capabilities": capabilities, "solutions": solutions}
    pages = {}
    for key, page in source_pages.items():
        main_page = page.select_one("main") or page
        pages[key] = {"headings": [clean(x) for x in main_page.select("h1,h2,h3") if clean(x)][:45],
                      "paragraphs": [clean(x) for x in main_page.select("p") if len(clean(x)) > 12][:35],
                      "images": [image_url(x) for x in main_page.select("img") if image_url(x)][:12]}
    footer = clean(contact.select_one("footer"))
    media = {item["image_url"] for item in product_rows + categories + articles if item["image_url"]}
    media.update(pages["about"]["images"][:2])
    media.update(pages["capabilities"]["images"][:3])
    media.update(pages["solutions"]["images"][:1])
    media.update(image_url(x) for x in home_main.select("img")[:5] if image_url(x))
    if logo and image_url(logo):
        media.add(image_url(logo))
    print(f"Downloading {len(media)} images", flush=True)
    with concurrent.futures.ThreadPoolExecutor(max_workers=5) as pool:
        assets = dict(pool.map(asset_record, sorted(media)))
    manifest = {"origin": ORIGIN, "categories": categories, "products": product_rows,
                "articles": articles, "pages": pages, "footer": footer,
                "home": {"hero_title": clean(home.select_one("h1")),
                         "images": [image_url(x) for x in home_main.select("img")[:5] if image_url(x)],
                         "logo_url": image_url(logo) if logo else ""}, "assets": assets}
    OUTPUT.mkdir(parents=True, exist_ok=True)
    (OUTPUT / "manifest.json").write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(f"Collected {len(categories)} categories, {len(product_rows)} products, {len(articles)} articles, {len(assets)} images")
    print(f"Assets: {sum(item['bytes'] for item in assets.values()) / 1024 / 1024:.2f} MiB")


if __name__ == "__main__":
    main()
