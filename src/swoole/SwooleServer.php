<?php
/**
 * Author: lf
 * Blog: https://blog.feehi.com
 * Email: job@feehi.com
 * Created at: 2017-08-20 12:01
 */

namespace feehi\swoole;


class SwooleServer extends \yii\base\Object
{
    public $swoole;

    public static $swooleConfig;

    public $runApp;

    public function __construct($host, $port, $swooleConfig=[])
    {
        $this->swoole = new \swoole_http_server($host, $port);
        self::$swooleConfig = $swooleConfig;
        $this->swoole->set($swooleConfig);
        $this->swoole->on('request', [$this, 'onRequest']);
        parent::__construct();
    }

    public function run()
    {
        $this->swoole->start();
    }

    /**
     * @param \swoole_http_request $request
     * @param \swoole_http_response $response
     */
    public function onRequest($request, $response)
    {
        //拦截无效请求
        //$this->rejectUnusedRequest($request, $response);

        //静态资源服务器
        //$this->staticRequest($request, $response);

        //转换$_FILE超全局变量
         $this->mountGlobalFilesVar($request, $response);


        call_user_func_array($this->runApp, [$request, $response]);
    }

    /**
     * @param \swoole_http_request $request
     * @param \swoole_http_response $response
     */
    private function rejectUnusedRequest($request, $response)
    {
        $uri = $request->server['request_uri'];
        $iru = strrev($uri);

        if( strpos('pam.', $iru) === 0 ){//.map后缀
            $response->status(200);
            $response->end('');
        }
    }

    /**
     * @param \swoole_http_request $request
     * @param \swoole_http_response $response
     */
    private function staticRequest($request, $response)
    {
        $uri = $request->server['request_uri'];
        $extension = pathinfo($uri, PATHINFO_EXTENSION);
        if( !empty($extension) && in_array($extension, ['js', 'css', 'jpg', 'jpeg', 'png', 'gif', 'webp']) ){

            $web = self::$swooleConfig['document_root'];
            rtrim($web, '/');
            $file = $web . '/' . $uri;
            if( is_file( $file )){
                $temp = strrev($file);
                if( strpos($uri, 'sj.') === 0 ) {
                    $response->header('Content-Type', 'application/x-javascript');
                }else if(strpos($temp, 'ssc.') === 0){
                    $response->header('Content-Type', 'text/css');
                }else {
                    $response->header('Content-Type', 'application/octet-stream');
                }
                $response->sendfile($file);
            }else{
                $response->status(404);
                $response->end('');
            }
        }
    }

    /**
     * @param \swoole_http_request $request
     */
    private function mountGlobalFilesVar($request)
    {
        if( isset($request->files) ) {
            $files = $request->files;
            foreach ($files as $k => $v) {
                if( isset($v['name']) ){
                    $_FILES = $files;
                    break;
                }
                foreach ($v as $key => $val) {
                    $_FILES[$k]['name'][$key] = $val['name'];
                    $_FILES[$k]['type'][$key] = $val['type'];
                    $_FILES[$k]['tmp_name'][$key] = $val['tmp_name'];
                    $_FILES[$k]['size'][$key] = $val['size'];
                    if(isset($val['error'])) $_FILES[$k]['error'][$key] = $val['error'];
                }
            }
        }
    }

}