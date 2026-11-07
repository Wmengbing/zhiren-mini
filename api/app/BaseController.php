<?php
namespace app;

use think\App;
use think\Request;

abstract class BaseController
{
    protected App $app;
    protected Request $request;

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->request = $app->request;
    }
}
