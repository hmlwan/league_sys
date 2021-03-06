<?php

//消息相关控制器
Class MemberAction extends CommonAction
{
    public function index(){

        $member = M('member');
        $username = session('username');
        $m_info = $member->where(array('username' => $username))->find();

        $this->assign('m_info',$m_info);
        //签到
        $sign_w = array();
        $sign_w['user_id'] =  $m_info['id'];
        $sign_w['add_time'] =  array(
            'between',array(
                strtotime(date("Y-m-d 00:00:00",time())),
                strtotime(date("Y-m-d 23:59:59",time()))
            )
        );
        $sign_info = M('member_sign_in')->where($sign_w)->find();
        $is_senior_cert = 0;
        $user_id = session('mid');
        $member_senior_info = M('member_senior_cert')->where(array('user_id'=>$user_id))->find();
        if($member_senior_info && $member_senior_info['is_senior_cert'] == 1){
            $is_senior_cert = 1;
        }
        $this->assign('sign_info',$sign_info);
        $this->assign('is_senior_cert',$is_senior_cert);
        $this->display();
    }

    public function sign_op(){

        $member = M('member');
        $username = session('username');
        $m_info = $member->where(array('username' => $username))->find();
        $sign_w = array();
        $sign_w['user_id'] =  $m_info['id'];
        $sign_w['add_time'] =  array(
            'between',array(
                strtotime(date("Y-m-d 00:00:00",time())),
                strtotime(date("Y-m-d 23:59:59",time()))
            )
        );
        $sign_info = M('member_sign_in')->where($sign_w)->find();
        if($sign_info){
            $this->ajaxReturn(array('info' => '今日已签到！', 'result' => -1));
        }
        $sign_range = getConf('sign_range');
        $sign_valid_period = getConf('sign_valid_period');
        $sign_reward = '0.00';
        if($sign_range){
            $sign_range_arr = explode('-',$sign_range);
            $sign_reward = randomFloat($sign_range_arr[0],$sign_range_arr[1]);
        }
        $r = M('member_sign_in')->add(array(
            'user_id' => $m_info['id'],
            'num' => $sign_reward,
            'add_time' => time(),
            'valid_period' => $sign_valid_period?$sign_valid_period:9999
        ));
        if(!$r){
            $this->ajaxReturn(array('info' => '签到失败！', 'result' => -1));
        }
        /*明细*/
        D('UserEcoLog')->changeUserNum($m_info['id'],array(
            'num' => $sign_reward,
            'remark' => '签到奖励'.$sign_reward.'生态积分',
            'type'=> 1,
            'valid_period' => $sign_valid_period
        ));
        $this->ajaxReturn(array('info' => "恭喜签到成功,获得{$sign_reward}生态积分({$sign_valid_period})", 'result' => 1));
    }


    /*高级实名认证*/
    public function senior_cert(){

        $member = D('Member');
        $user_id = session('mid');
        $info = $member->getByUserId($user_id);

        $cert_info = M('member_senior_cert')->where(array('user_id'=>$user_id))->find();
        $this->assign('info',$info);
        $this->assign('cert_info',$cert_info);
        $this->display();
    }
    /*高级实名操作*/
    public function senior_cert_op(){
        $member_cert = M('member_senior_cert');
        $user_id = session('mid');
        $cert_info = $member_cert->where(array('user_id'=>$user_id))->find();

        $id_card_reverse = I('id_card_reverse','');

        if($cert_info['is_senior_cert' == '1']){
            $this->ajaxReturn(array('info' => '已认证成功!'));
        }
        if($cert_info['is_senior_cert' == '2']){
            $this->ajaxReturn(array('info' => '已在认证中!'));
        }
        if(!$id_card_reverse){
            $this->ajaxReturn(array('info' => '请上传身份证反面证件!'));
        }
        if($cert_info){
            $r = $member_cert->where(array('user_id'=>$user_id))->save(array(
                'id_card_reverse' =>  $id_card_reverse,
                'cert_fail_reason' =>  '',
                'is_senior_cert' =>  2,
            ));
        }else{
            $r = $member_cert->add(array(
                'user_id' => $user_id,
                'id_card_reverse' =>  $id_card_reverse,
                'is_senior_cert' =>  2,
                'add_time' =>  time(),
            ));
        }

        if(false === $r){
            $this->ajaxReturn(array('info' => '提交认证失败!'));
        }
        $this->ajaxReturn(array('info' => '提交认证成功！', 'result' => 1, 'toUrl' => U('/index/member/senior_cert')));
    }
    /*认证说明*/
    public function senior_cert_intro(){
        $this->display();
    }

    /*修改登录密码*/
    public function edit_pwd(){
        header("Content-type:text/html;charset=utf-8");
        if (IS_POST) {
            $type = I('type','');
            if(!$type){
                $this->ajaxReturn(array('info' => '未知错误!'));
            }
            //验证用户名和密码
            $mobile = $_POST['username'];
            if(!$mobile){
                $this->ajaxReturn(array('info' => '请输入手机号码!'));
            }
            if (!preg_match("/^(1)[0-9]{10}$/", $mobile)) {
                $this->ajaxReturn(array('info' => '手机号码格式不正确!'));
            }
            if (!M('member')->where(array('username' => trim($mobile)))->getField('id')) {
                $this->ajaxReturn(array('info' => '手机号不存在！'));
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
            if($type == 1){
                $data['password'] = $password;
                $toUrl = U('index/index/logout');
            }else{
                $data['trade_password'] = $password;
                $toUrl = '';
            }
            $mem_db  = M('member');
            $r = $mem_db->where(array('username' => $mobile))->save($data);

            $this->ajaxReturn(array('info' => '修改成功！', 'result' => 1, 'toUrl' => $toUrl));
        }else{
            $this->assign('mobile', session('username'));
            $this->display();
        }
    }

    /*修改支付密码*/
    public function edit_trade_pwd(){
        $this->assign('mobile', session('username'));
        $this->display();
    }
    /*公告*/
    public function announce(){
        $announce_m = M('announce');
        $where = array();
        $where['tid'] = 1;
        $count = $announce_m->where($where)->count();
        $Page  = new Page($count,4);
        $list = $announce_m->where($where)
            ->limit ( $Page->firstRow . ',' . $Page->listRows )
            ->order('edittime desc')
            ->select();
        $this->assign('list', $list);
        if(IS_AJAX){
            $data['content'] = $this->fetch('ajax_announce');
            $data['count'] = array(
                'totalRows'=> $count,
                'listRows'=> 4,
            );
            $this->ajaxReturn($data);
        }else{
            $this->display();
        }
    }
    public function help(){
        $this->display();
    }
    /*邀请好友*/
    public function invite(){
        $member = M('member');
        $username = session('username');
        $inviteCode = $member->where(array('username' => $username))->getField('inviteCode');
        $qrcode_url = C('API_URL').'/index/login/reg?invite_code='.$inviteCode;
        $this->assign('inviteCode', $inviteCode);
        $this->assign('qrcode_url', $qrcode_url);
        $this->display();

    }
    public function qrcodeimg(){
        Vendor('phpqrcode.phpqrcode');
        $QRcode = new \QRcode ();
        $errorCorrectionLevel = 'L';
        $matrixPointSize = 4;
        $member = M('member');
        $username = session('username');
        $inviteCode = $member->where(array('username' => $username))->getField('inviteCode');
        $qrcode_url = C('API_URL').'/index/login/reg?invite_code='.$inviteCode;
        $QRcode::png($qrcode_url,false,$errorCorrectionLevel,$matrixPointSize);
    }
    /*我的团队*/
    public function team(){
        $user_id = session('mid');

        $UserStatistics = D('UserStatistics');
        $info = $UserStatistics->getByUserId($user_id);

        $this->assign('info', $info);
        $invite_record_m = M('invite_record');
        $invite_record_w = array(
            'user_id' => $user_id,
            'level' => 1,
            'types' => 1
        );
        $count = $invite_record_m->where($invite_record_w)->count();
        $Page  = new Page($count,6);
        $list = $invite_record_m->where($invite_record_w)
            ->limit ( $Page->firstRow . ',' . $Page->listRows )
            ->order('add_time desc')
            ->select();
        foreach ($list as &$value){
            $one_sub_cert_nums = M('user_statistics')
                ->where(array('user_id'=>$value['sub_user_id']))
                ->getField('one_sub_cert_nums');
            $value['one_sub_cert_nums'] = $one_sub_cert_nums?$one_sub_cert_nums:0;
        }
        $this->assign('list', $list);
        if(IS_AJAX){
            $data['content'] = $this->fetch('ajax_team');
            $data['count'] = array(
                'totalRows'=> $count,
                'listRows'=> 6,
            );
            $this->ajaxReturn($data);
        }else{
            $this->display();
        }
    }
    /*生态积分*/
    public function jifen(){
        $member = D('Member');
        $user_id = session('mid');

        $types = I('get.type','');
        $info = $member->getByUserId($user_id);

        $this->assign('info', $info);
        $UserEcoLog_m = D('UserEcoLog');
        $w = array();
        if($types == 1){ //有效
            $w['types'] = array('neq',4);
        }elseif($types == 2){ //无效
            $w['types'] = array('eq',4);
        }
        $count = $UserEcoLog_m->where($w)->count();
        $Page  = new Page($count,6);
        $list = $UserEcoLog_m->where($w)
            ->limit ( $Page->firstRow . ',' . $Page->listRows )
            ->order('create_time desc')
            ->select();
        foreach ($list as &$value){
            if($value['num'] > 0){
                $value['num'] = "+".$value['num'];
            }
            $value['type_name'] =  $UserEcoLog_m->getType($value['types']);
        }
        $this->assign('list', $list);
        if(IS_AJAX){
            $data['content'] = $this->fetch('ajax_jifen');
            $data['count'] = array(
                'totalRows'=> $count,
                'listRows'=> 6,
            );
            $this->ajaxReturn($data);
        }else{
            $this->display();
        }
    }
    /*联盟积分*/
    public function league(){
        $member = D('Member');
        $user_id = session('mid');
        $types = I('get.type','');
        $info = $member->getByUserId($user_id);

        $this->assign('info', $info);
        $UserLeagueLog_m = D('UserLeagueLog');
        $w = array(
            'num'=>array('neq',0)
        );
        if($types == 1){ //收入
            $w['types'] = array('eq',1);
        }elseif($types == 2){ //支出
            $w['types'] = array('neq',1);
        }
        $count = $UserLeagueLog_m->where($w)->count();
        $Page  = new Page($count,6);
        $list = $UserLeagueLog_m->where($w)
            ->limit ( $Page->firstRow . ',' . $Page->listRows )
            ->order('create_time desc')
            ->select();
        foreach ($list as &$value){
            if($value['num'] > 0){
                $value['num'] = "+".$value['num'];
            }
            $value['type_name'] =  $UserLeagueLog_m->getType($value['types']);
        }
        $this->assign('list', $list);
        if(IS_AJAX){
            $data['content'] = $this->fetch('ajax_league');
            $data['count'] = array(
                'totalRows'=> $count,
                'listRows'=> 6,
            );
            $this->ajaxReturn($data);
        }else{
            $this->display();
        }
    }

    /*消费订单*/
    public function order(){
        $this->display();
    }
    public function order_op(){
        if(IS_POST){
            $scene = I('scene');
            $order_no = trim(I('order_no'));
            if(!$scene){
                $this->ajaxReturn(array('info' => '请选择平台！'));
            }
            if(!$order_no){
                $this->ajaxReturn(array('info' => '请输入订单号！'));
            }
            $MemberRebateOrder = D('MemberRebateOrder');
            $w = array(
                'scene' => $scene,
                'order_no' => $order_no,
            );
            $info = $MemberRebateOrder->getInfo($w);
            if($info){
                $this->ajaxReturn(array('info' => '该订单已存在！'));
            }
            $RebateOnlineOrder = D('RebateOnlineOrder');
            $user_id = session('mid');
            $online_info =  $RebateOnlineOrder->get_info($scene,$order_no);
            $add_data = array(
                'user_id' => $user_id,
                'order_no' => $order_no,
                'add_time' => time(),
                'is_receive' => 0,
                'status' => 0,
                'scene' => $scene,
            );
            if($online_info){
                $add_data['img'] = $online_info['img'];
                $add_data['title'] = $online_info['title'];
                $add_data['order_time'] = $online_info['order_time'];
                $add_data['commission'] = $online_info['commission'];
                $add_data['price'] = $online_info['price'];
                $add_data['status'] = $online_info['status'];
                $add_data['num'] = $online_info['num'];
            }
            $r = $MemberRebateOrder->addInfo($add_data);

            if(!$r){
                $this->ajaxReturn(array('info' => '绑定失败！'));
            }
            $this->ajaxReturn(array('info' => "绑定成功！", 'result' => 1, 'toUrl' => U('/index/member/order_detail')));

        }
    }
    /*全部订单*/
    public function order_detail(){
        $user_id = session('mid');
        $status = I('status');
        $w = array(
            'user_id' => $user_id
        );
        if($status){
            $w['status'] = $status;
        }
        $MemberRebateOrder = D('MemberRebateOrder');
        $count = $MemberRebateOrder->where($w)->count();
        $Page  = new Page($count,6);
        $list = $MemberRebateOrder->where($w)
            ->limit ( $Page->firstRow . ',' . $Page->listRows )
            ->order('status asc,add_time desc')
            ->select();
        foreach ($list as &$value){
            $value['scene_name'] =  $MemberRebateOrder->getScencName($value['scene']);
        }
        $this->assign('list', $list);
        if(IS_AJAX){
            $data['content'] = $this->fetch('ajax_order_detail');
            $data['count'] = array(
                'totalRows'=> $count,
                'listRows'=> 6,
            );
            $this->ajaxReturn($data);
        }else{
            $this->display();
        }
    }
    //ANE钱包
    public function wallet(){
        $member = D('Member');
        $user_id = session('mid');

        $types = I('get.type','');
        $info = $member->getByUserId($user_id);

        $this->assign('info', $info);
        $UserAneLog_m = D('UserAneLog');
        $w = array();
        if($types == 1){ //收入
            $w['num'] = array('gt',0);
        }elseif($types == 2){ //支出
            $w['num'] = array('lt',0);
        }
        $count = $UserAneLog_m->where($w)->count();
        $Page  = new Page($count,6);
        $list = $UserAneLog_m->where($w)
            ->limit ( $Page->firstRow . ',' . $Page->listRows )
            ->order('create_time desc')
            ->select();
        foreach ($list as &$value){
            if($value['num'] > 0){
                $value['num'] = "+".$value['num'];
            }
            $value['type_name'] =  $UserAneLog_m->getType($value['types']);
        }
        $this->assign('list', $list);
        if(IS_AJAX){
            $data['content'] = $this->fetch('ajax_wallet');
            $data['count'] = array(
                'totalRows'=> $count,
                'listRows'=> 6,
            );
            $this->ajaxReturn($data);
        }else{
            $this->display();
        }
    }
    //收款管理
    public function payment(){

        $member = D('Member');
        $user_id = session('mid');
        $type = I('get.type');
        $info = $member->getByUserId($user_id);
        $this->assign('info',$info);
        $this->assign('type',$type);
        $this->display();
    }
    public function payment_op(){
        $member = D('Member');
        $user_id = session('mid');
        $type = I('type');
        $account = I('account');
        $upload_img = I('upload_img');
        if(!$account){
            $this->ajaxReturn(array('info' => '请输入账号！'));
        }
        if(!$upload_img){
            $this->ajaxReturn(array('info' => '请上传收款码！'));
        }
        $save_data = array(
            'zfb' => $account,
            'zfb_img' => $upload_img,
        );
        if($type == 'wx'){
            $save_data = array(
                'wx' => $account,
                'wx_img' => $upload_img,
            );
        }
        $r = $member->saveInfo($user_id,$save_data);
        if(!$r){
            $this->ajaxReturn(array('info' => '修改失败！'));
        }
        $this->ajaxReturn(array('info' => "修改成功！", 'result' => 1, 'toUrl' => U('/index/member/payment',array('type'=>$type))));
    }

}

?>