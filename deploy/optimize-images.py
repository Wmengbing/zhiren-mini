"""Generate mobile previews while retaining original product/certificate images.
Run: python -m pip install Pillow
     python deploy/optimize-images.py
"""
from pathlib import Path
from hashlib import sha256
from PIL import Image, ImageOps

root = Path(__file__).resolve().parents[1] / "api/public/uploads"
output = root / "optimized"
output.mkdir(parents=True, exist_ok=True)
count = saved = 0
for source in root.rglob("*"):
    if not source.is_file() or output in source.parents or source.suffix.lower() not in {".jpg", ".jpeg", ".png", ".webp"}:
        continue
    path = "/uploads/" + source.relative_to(root).as_posix()
    key = sha256(path.encode()).hexdigest()[:24]
    try:
        with Image.open(source) as original:
            image = ImageOps.exif_transpose(original).convert("RGB")
            for variant, width in (("card", 480), ("hero", 1280)):
                target = output / (variant + "-" + key + ".jpg")
                if target.exists() and target.stat().st_mtime >= source.stat().st_mtime:
                    continue
                preview = image.copy()
                preview.thumbnail((width, width))
                preview.save(target, "JPEG", quality=80, optimize=True)
                saved += max(0, source.stat().st_size - target.stat().st_size)
                count += 1
    except (OSError, ValueError) as error:
        raise RuntimeError("Cannot optimize " + str(source)) from error
print("Generated", count, "mobile images; cumulative transfer reduction:", saved, "bytes.")
