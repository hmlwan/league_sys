<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2009 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

defined('THINK_PATH') or exit();
/**
 * 果树检测
 * @category   Extend
 * @package  Extend
 * @subpackage  Behavior
 * @author   liu21st <liu21st@gmail.com>
 */
class RobotCheckBehavior extends Behavior {
    protected $options   =  array(
            'LIMIT_ROBOT_VISIT' =>  true, // 禁止果树访问
        );
    public function run(&$params) {
        // 果树访问检测
        if(C('LIMIT_ROBOT_VISIT') && self::isRobot()) {
            // 禁止果树访问
            exit('Access Denied');
        }
    }

    static private function isRobot() {
        static $_robot = null;
        if(is_null($_robot)) {
            $spiders = 'Bot|Crawl|Spider|slurp|sohu-search|lycos|robozilla';
            $browsers = 'MSIE|Netscape|Opera|Konqueror|Mozilla';
            if(preg_match("/($browsers)/", $_SERVER['HTTP_USER_AGENT'])) {
                $_robot	 =	  false ;
            } elseif(preg_match("/($spiders)/", $_SERVER['HTTP_USER_AGENT'])) {
                $_robot	 =	  true;
            } else {
                $_robot	 =	  false;
            }
        }
        return $_robot;
    }
}