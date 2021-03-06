<?php

/**
 * 会员前台登录控制器
 */
Class LoginAction extends Action
{

  public function _initialize()
  {
    //判断是否关闭了网站1
    $open_web = C('open_web');
    if (empty($open_web)) {
      $this->open_web_notice = C('open_web_notice');
      $this->display('Index:404');
      exit;
    }
  }

  /**
   * 会员登录视图
   * @return [type] [description]
   */
  public function index()
  {

    if (IS_AJAX) {

      //验证系统是否为开放状态
      if (C('MEMBER_LOGIN') == 'off') {
        $this->ajaxReturn(array('result' => '0', 'info' => '系统暂未开放！'));
      }

      if (I('username') == "" || I('password') == "") {
        $this->ajaxReturn(array('result' => '0', 'info' => '用户名和密码不能为空！'));
      }


      $model_m = M('member');
      //验证用户名和密码
      $member = $model_m
          ->where(array(
              'username' => I('username'),
              'password' => I('password', '', 'md5')))
          ->find();
      if (!$member) {
        $this->ajaxReturn(array('result' => '0', 'info' => '用户名或密码错误!'));
      }
      //禁止锁定会员登录
      if ($member['lock']) {
        $this->ajaxReturn(array('result' => '0', 'info' => '您的账号已经被锁定!联系客服'));
      }

      //更新上一次IP和登录时间
      $prologin['preloginip'] = $member['loginip'];
      $prologin['preloginaddress'] = '';
      $prologin['prelogintime'] = $member['logintime'];
      $prologin['logintime'] = time();

      $model_m->where(array('id' => $member['id']))->save($prologin);
      //更新最后一次登录的IP和登录时间
      //$area = $Ip->getlocation(get_client_ip());
      //$area = get_ip_address(get_client_ip());

//      $data = array(
//        'id' => $member['id'],
//        'logintime' => time(),
//        'loginip' => '',
//        'loginaddress' => ''
//      );
//      $model_m->save($data);

      //添加登录总次数
      $model_m->where(array('username' => I('username')))->setInc('logincount');
      //保存session
      session('mid', $member['id']);
      session('username', $member['username']);
      session('member', 'memberlogin');

      $remember = I("post.remember", 0, 'intval');
      $mypassword = I('post.password');
      if (!empty($remember)) {
        setcookie('rememberusername', $member['username'], time() + 3600 * 24 * 30);
        setcookie('rememberpassword', $mypassword, time() + 3600 * 24 * 30);

      } else {
        setcookie('rememberusername', null, time() - 3600 * 24 * 30);
        setcookie('rememberpassword', null, time() - 3600 * 24 * 30);

      }
      if($member['is_cert'] == 1){ //已实名
          $toUrl = U('Index/Member/index');
          $info = '登陆成功！';
      }else{ //未实名
          $toUrl = U('Index/Login/cert');
          $info = '登录成功，请先去实名认证！';
      }
      $this->ajaxReturn(array('result' => '1', 'info' => $info, 'toUrl' => $toUrl));

    } else {

      $rememberusername = $_COOKIE['rememberusername'];
      $rememberpassword = $_COOKIE['rememberpassword'];
      if (!empty($_COOKIE['rememberusername'])) {
        $rememberusername = $_COOKIE['rememberusername'];
      }
      if (!empty($_COOKIE['rememberpassword'])) {
        $rememberpassword = $_COOKIE['rememberpassword'];
      }
      $shipin = C('shipin');
      $this->assign('shipin', $shipin);
      $this->assign('rememberusername', $rememberusername);
      $this->assign('rememberpassword', $rememberpassword);
      $this->display();
    }
  }

  //修改密码
  public function editPwd()
  {
    header("Content-type:text/html;charset=utf-8");
    if (IS_POST) {
    	
      $model_m = M('member');
      //验证用户名和密码

      $member = $model_m->where(array('username' => I('username'), 'password' => I('post.oldpassword', '', 'md5')))->find();
      $mobile = $_POST['username'];
      if(!$mobile){
          $this->ajaxReturn(array('info' => '请输入手机号码!'));
      }
      if (!preg_match("/^(1)[0-9]{10}$/", $mobile)) {
        $this->ajaxReturn(array('info' => '手机号码格式不正确!'));
      }
      if (!M('member')->where(array('username' => trim($mobile)))->getField('id')) {
        $this->ajaxReturn(array('info' => '手机号不存在，请重新输入！'));
      }
      $code = I('post.code', '');
      if (!$code) {
        $this->ajaxReturn(array('info' => '请输入短信验证码!'));
      }
      $check_code = sms_code_verify($mobile, $code, session_id());
      if ($check_code['status'] != 1) {
        $this->ajaxReturn(array('info' => $check_code['msg']));
      }
      $password = I('post.password', '', 'md5');
      $repassword = I('post.repassword', '', 'md5');
      if ($password != $repassword) {
        $this->ajaxReturn(array('info' => '密码和确认密码不一致！'));
      }
      //开始修改密码
      $data = array();
      $data['password'] = $password;
      M('member')->where(array('username' => $mobile))->save($data);
      $this->ajaxReturn(array('info' => '修改成功！', 'result' => 1, 'toUrl' => U('index')));
    }
    $this->display();
  }

  public function reg()
  {
      $invite_code = I('get.invite_code');
      $this->assign('invite_code',$invite_code);
      $this->display();
  }

  //前台注册
  public function regSempost()
  {

    if (IS_AJAX) {
      import('ORG.Net.IpLocation');// 导入IpLocation类
      $Ip = new IpLocation(); // 实例化类
      $location = $Ip->getlocation(get_client_ip()); // 获取某个IP地址所在的位置

      $invite_code = I('post.invite_code', '', 'strval');

      if (empty($invite_code)) {
        $this->ajaxReturn(array('result' => 0, 'info' => '推荐人不能为空!'));
      }
      $parent = M('member')->where(array('inviteCode' => $invite_code))->getField('username');
      $data['parent_id'] = M('member')->where(array('username' => $parent))->getField('id');
      $data['parent'] = $parent;

      $data['username'] =  I('post.username', '', 'strval');
      $code = I('post.code', '');

      $data['name'] = I('post.name');
      $password = I('post.password', '', 'strval');
	  $repassword = I('post.repassword', '', 'strval');
      if (empty($password)) {
         $this->ajaxReturn(array('result' => 0, 'info' => '登陆密码不能为空'));
      }
	  if($password != $repassword){
	  	   $this->ajaxReturn(array('result' => 0, 'info' => '密码不一致'));
	  }

      //验证推荐人信息是否已存在及审核
      if (!M('member')->where(array('username' => $data['parent']))->getField('id')) {
        $this->ajaxReturn(array('result' => 0, 'info' => '邀请码不存在'));
      }
      if (empty($data['username'])) {
        $this->ajaxReturn(array('result' => 0, 'info' => '请填写手机号码!'));
      }

      if (!preg_match("/^1[34578]{1}\d{9}$/", $data['username'])) {
        $this->ajaxReturn(array('result' => 0, 'info' => '手机号码格式不正确!'));
      }

      if (M('member')->where(array('username' => trim($data['username'])))->getField('id')) {
        $this->ajaxReturn(array('result' => 0, 'info' => '该用户已存在，请更换！'));
      }


      if (!$code) {
        $this->ajaxReturn(array('result' => 0, 'info' => '请输入短信验证码!'));
      }
      $check_code = sms_code_verify($data['username'], $code, session_id());
      if ($check_code['status'] != 1) {
        $this->ajaxReturn(array('result' => 0, 'info' => $check_code['msg']));
      }



      $hongbao = C('hongbao');
      $data['regaddress'] = $location['country'] . $location['area']; // 所在国家或者地区
      $data['regip'] = get_client_ip(); // 所在国家或者地区
//      $data['idcard'] = I('post.idcard');
      $data['password'] = md5($password);
//      $data['money'] = $hongbao;
      $parentinfo = M('member')->where(array('username' => $data['parent']))->find();
      $data['parentpath'] = trim($parentinfo['parentpath'] . $parentinfo['id'] . '|');;
      $data['parentlayer'] = $parentinfo['parentlayer'] + 1;
      $data['regdate'] = time();
      $inviteCode = $this->checkInviteCode($this->makeCode());
      $data['inviteCode'] = $inviteCode;
      $r = M('member')->add($data);
      //统计
      if($r > 0){
          $member = D('Member');
          $user_statistics_m = D('UserStatistics');
          $invite_record_m = D('InviteRecord');
          $info = $member->getByUserMobile($data['username']);
          if($info['parent_id'] >0 ){
              $info_1 = $member->getByUserId($info['parent_id']);
              if($info_1 && $info_1['is_cert'] == 1){
                  $user_statistics_m->setFieldInc($info_1['id'],'one_sub_nocert_nums',1);

                  $invite_record_1 = $invite_record_m->addRecord(array(
                      'user_id' => $info_1['id'],
                      'sub_user_id' => $info['id'],
                      'add_time' => time(),
                      'is_cert' => 1,
                      'sub_mobile' => $info['username'],
                      'level' => 1,
                      'content' => '一级下线实名',
                      'types' => 1
                  ));
                  if($info_1['parent_id'] >0){
                      $info_2 = $member->getByUserId($info_1['parent_id']);
                      if($info_2 && $info_2['is_cert'] == 1){
                          $user_statistics_m->setFieldInc($info_2['id'],'two_sub_nocert_nums',1);

                          $invite_record_2 = $invite_record_m->addRecord(array(
                              'user_id' => $info_2['id'],
                              'sub_user_id' => $info['id'],
                              'add_time' => time(),
                              'is_cert' => 1,
                              'sub_mobile' => $info['username'],
                              'level' => 2,
                              'types' => 1,
                              'content' => '二级下线实名',
                          ));
                      }
                  }
              }
          }
      }
      //我的上级直推加一
//      M('member')->where(array('username' => $data['parent']))->setInc('parentcount', 1);
//      mmtjrennumadd($data['parent_id']);//  所有上级加一人
      $this->ajaxReturn(array('result' => 1, 'info' => '注册成功！','toUrl'=>U('index')));
    }
  }

    /*实名页面*/
    public function cert(){
        $member = M('member');
        $username = session('username');
        $m_info = $member->where(array('username' => $username))->find();
        if($m_info['is_cert'] == 1){
            $this->redirect('index/member/index');
        }
        $this->assign('m_info',$m_info);
        $this->display();
    }

    /*实名处理*/
    public function cert_op(){
        $member = D('Member');
        $username = session('username');
        $info = $member->getByUserMobile($username);

        $truename = I('truename','');
        $idcard = I('idcard','');
        if(!$truename || !$idcard){
            $this->ajaxReturn(array('info' => '姓名和身份证必填！', 'result' => -1));
        }

        $password = I('post.trade_password', '', 'strval');
        $repassword = I('post.trade_repassword', '', 'strval');
        if (empty($password)) {
            $this->ajaxReturn(array('result' => 0, 'info' => '交易密码不能为空'));
        }
        if($password != $repassword){
            $this->ajaxReturn(array('result' => 0, 'info' => '密码不一致'));
        }
        $code = I('post.code', '');

        if (!$code) {
            $this->ajaxReturn(array('result' => 0, 'info' => '请输入短信验证码!'));
        }
        $check_code = sms_code_verify($info['username'], $code, session_id());
        if ($check_code['status'] != 1) {
            $this->ajaxReturn(array('result' => 0, 'info' => $check_code['msg']));
        }
        $cert_api_r = cert_api(array('idcard'=>$idcard,'name'=>$truename));
        if(!$cert_api_r){
            $member->where(array('username'=>$username))->save(array('is_cert' => 2));
            $this->ajaxReturn(array('info' => '实名失败！', 'result' => -1));
        }
        $member->where(array('username'=>$username))->save(array(
             'is_cert' => 1,
             'truename' => $truename,
             'idcard' => $idcard,
             'card' => I('card'),
             'trade_password' => md5(I('trade_password')),
            )
        );
        $user_statistics_m = D('UserStatistics');
        $invite_record_m = D('InviteRecord');
        if($info['parent_id'] >0 ){
            $info_1 = $member->getByUserId($info['parent_id']);
            if($info_1 && $info_1['is_cert'] == 1){
                $user_statistics_m->setFieldInc($info_1['id'],'one_sub_cert_nums',1);
                $invite_record_1 = $invite_record_m->editRecord(array(
                    'user_id' => $info_1['id'],
                    'sub_user_id' => $info['id'],
                    'level' => 1,
                    'types' => 1
                ),array(
                    'user_id' => $info_1['id'],
                    'sub_user_id' => $info['id'],
                    'add_time' => time(),
                    'is_cert' => 2,
                    'sub_mobile' => $info['username'],
                    'level' => 1,
                    'content' => '一级下线实名',
                    'types' => 1
                ));
                if($info_1['parent_id'] >0){
                    $info_2 = $member->getByUserId($info_1['parent_id']);
                    if($info_2 && $info_2['is_cert'] == 1){
                        $user_statistics_m->setFieldInc($info_2['id'],'two_sub_cert_nums',1);
                        $invite_record_2 = $invite_record_m->editRecord(array(
                            'user_id' => $info_2['id'],
                            'sub_user_id' => $info['id'],
                            'level' => 2,
                            'types' => 1,
                        ),array(
                            'user_id' => $info_2['id'],
                            'sub_user_id' => $info['id'],
                            'add_time' => time(),
                            'is_cert' => 2,
                            'sub_mobile' => $info['username'],
                            'level' => 2,
                            'types' => 1,
                            'content' => '二级下线实名',
                        ));
                    }
                }
            }
        }
        /*实名奖励*/
        $cert_reward = getConf('cert_reward');
        if($cert_reward > 0){
            /*明细*/
            D('UserEcoLog')->changeUserNum($info['id'],array(
                'num' => $cert_reward,
                'remark' => '实名奖励'.$cert_reward.'生态积分',
                'type'=> 1,
                'valid_period' => 9999
            ));
        }
        $this->ajaxReturn(array('info' => '实名成功！', 'result' => 1, 'toUrl' => U('/index/member/index')));

    }
  public function checkInviteCode($code)
  {
    $tmp = M('member')->where(array('inviteCode' => $code))->find();
    if ($tmp) {
        $this->checkInviteCode($this->makeCode());
    } else {
        return $code;
    }
  }

  public function makeCode()
  {
    for ($i = 0, $tmp = ''; $i < 3; $i++) {
      $tmp .= chr(mt_rand(65, 90));
    }
    for ($i = 0, $numTmp = ''; $i < 3; $i++) {
      $numTmp .= mt_rand(0, 9);
    }
    return $tmp . $numTmp;
  }

  /**
   * 生成验证码
   */
  public function verify()
  {
    import('ORG.Util.Image');
    Image::buildImageVerify(4, 1, 'png', 55, 25);
  }

  public function showcode()
  {
    $this->display();
  }

  //验证码验证
  public function checkVerify($code)
  {
    if (session('verify') != $code) {
      alert('验证码错误', -1);
    }
  }

  public function checkUsername($username)
  {
    if (!$id = M('member')->where(array('username' => $username))->getField('id')) {
      alert('您输入的会员账号不存在！', -1);
    } else {
      return $id;
    }
  }


}

?>