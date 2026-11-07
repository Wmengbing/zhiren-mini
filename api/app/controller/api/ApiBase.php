<?php
namespace app\controller\api;

use app\BaseController;
use think\Response;

class ApiBase extends BaseController
{
    protected function ok($data = null, string $message = 'success'): Response
    {
        return json(['code' => 0, 'message' => $message, 'data' => $data]);
    }

    protected function fail(string $message, int $httpStatus = 400, int $code = 1): Response
    {
        return json(['code' => $code, 'message' => $message, 'data' => null], $httpStatus);
    }

    protected function page(): array
    {
        $page = max(1, min(100000, (int) $this->request->get('page', 1)));
        $size = max(1, min(50, (int) $this->request->get('page_size', 10)));
        return [$page, $size];
    }

    protected function input(): array
    {
        $json = json_decode((string) $this->request->getContent(), true);
        return is_array($json) ? $json : $this->request->post();
    }
}
