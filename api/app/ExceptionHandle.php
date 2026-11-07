<?php
namespace app;

use think\exception\Handle;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\Response;
use Throwable;

class ExceptionHandle extends Handle
{
    public function render($request, Throwable $exception): Response
    {
        if ($exception instanceof HttpResponseException) return $exception->getResponse();
        $status = $exception instanceof HttpException ? $exception->getStatusCode()
            : ($exception instanceof ValidateException ? 400 : 500);
        $message = $status === 500 && !$this->app->isDebug()
            ? '服务器内部错误' : $exception->getMessage();
        return json(['code' => $status, 'message' => $message, 'data' => null], $status);
    }
}
