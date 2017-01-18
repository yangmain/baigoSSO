<?php
/*-----------------------------------------------------------------
！！！！警告！！！！
以下为系统文件，请勿修改
-----------------------------------------------------------------*/

//不能非法包含或直接执行
if (!defined("IN_BAIGO")) {
    exit("Access Denied");
}

/*-------------用户类-------------*/
class CONTROL_API_SYNC {

    function __construct() { //构造函数
        $this->obj_api      = new CLASS_API();
        $this->obj_api->chk_install();

        $this->log          = $this->obj_api->log;

        $this->obj_crypt    = $this->obj_api->obj_crypt;
        $this->obj_sign     = $this->obj_api->obj_sign;

        $this->mdl_user     = new MODEL_USER(); //设置管理组模型
        $this->mdl_user_api = new MODEL_USER_API(); //设置管理组模型
        $this->mdl_app      = new MODEL_APP(); //设置管理组模型
        $this->mdl_belong   = new MODEL_BELONG();
        $this->mdl_log      = new MODEL_LOG(); //设置管理员模型
    }


    function ctrl_login() {
        $_arr_apiChks = $this->obj_api->app_chk("post");
        if ($_arr_apiChks["rcode"] != "ok") {
            $this->obj_api->show_result($_arr_apiChks);
        }

        $this->user_check();

        $_arr_sign = array(
            "act"                       => $GLOBALS["act"],
            $this->userInput["user_by"] => $this->userInput["user_str"],
            "user_access_token"         => $this->userInput["user_access_token"],
        );

        if (!$this->obj_sign->sign_check(array_merge($_arr_apiChks["appInput"], $_arr_sign), $_arr_apiChks["appInput"]["signature"])) {
            $_arr_return = array(
                "rcode" => "x050403",
            );
            $this->obj_api->show_result($_arr_return);
        }

        $this->app_list($_arr_apiChks["appInput"]["app_id"]);

        $_arr_code    = $this->userRow;
        $_arr_urlRows = array();

        foreach ($this->appRows as $_key=>$_value) {
            $_str_appKey            = fn_baigoCrypt($_value["app_key"], $_value["app_name"]);
            $_arr_code["app_id"]    = $_value["app_id"];
            $_arr_code["app_key"]   = $_str_appKey;

            //unset($_arr_code["rcode"]);
            $_str_src               = $this->obj_api->encode_result($_arr_code);
            $_str_code              = $this->obj_crypt->encrypt($_str_src, $_str_appKey);

            $_tm_time               = time();

            if (stristr($_value["app_url_sync"], "?")) {
                $_str_conn = "&";
            } else {
                $_str_conn = "?";
            }
            $_str_url = $_value["app_url_sync"] . $_str_conn . "mod=sync";

            $_arr_data = array(
                "act"   => "login",
                "time"  => $_tm_time,
                "code"  => $_str_code,
            );

            $_arr_data["signature"] = $this->obj_sign->sign_make($_arr_data);

            $_arr_urlRows[] = $_str_url . "&" . http_build_query($_arr_data);
        }

        $_arr_tplData = array(
            "rcode"      => "y100401",
            "urlRows"    => $_arr_urlRows,
        );
        $this->obj_api->show_result($_arr_tplData);
    }


    function ctrl_logout() {
        $_arr_apiChks = $this->obj_api->app_chk("post");
        if ($_arr_apiChks["rcode"] != "ok") {
            $this->obj_api->show_result($_arr_apiChks);
        }

        $this->user_check();

        $_arr_sign = array(
            "act"                       => $GLOBALS["act"],
            $this->userInput["user_by"] => $this->userInput["user_str"],
            "user_access_token"         => $this->userInput["user_access_token"],
        );

        if (!$this->obj_sign->sign_check($_arr_sign, $_arr_apiChks["appInput"]["signature"])) {
            $_arr_return = array(
                "rcode" => "x050403",
            );
            $this->obj_api->show_result($_arr_return);
        }

        $this->app_list($_arr_apiChks["appInput"]["app_id"]);

        $_arr_code    = $this->userRow;
        $_arr_urlRows = array();

        foreach ($this->appRows as $_key=>$_value) {
            $_str_appKey            = fn_baigoCrypt($_value["app_key"], $_value["app_name"]);

            $_arr_code["app_id"]    = $_value["app_id"];
            $_arr_code["app_key"]   = $_str_appKey;

            //unset($_arr_code["rcode"]);
            $_str_src               = $this->obj_api->encode_result($_arr_code);
            $_str_code              = $this->obj_crypt->encrypt($_str_src, $_str_appKey);

            $_tm_time               = time();

            if (stristr($_value["app_url_sync"], "?")) {
                $_str_conn = "&";
            } else {
                $_str_conn = "?";
            }
            $_str_url = $_value["app_url_sync"] . $_str_conn . "mod=sync";

            $_arr_data = array(
                "act"   => "logout",
                "time"  => $_tm_time,
                "code"  => $_str_code,
            );

            $_arr_data["signature"] = $this->obj_sign->sign_make($_arr_data);

            $_arr_urlRows[] = $_str_url . "&" . http_build_query($_arr_data);
        }

        $_arr_tplData = array(
            "rcode"      => "y100402",
            "urlRows"    => $_arr_urlRows,
        );
        $this->obj_api->show_result($_arr_tplData);
    }


    private function app_list($num_appId) {
        $_arr_search = array(
            "status"        => "enable",
            "sync"          => "on",
            "has_sync"      => true,
            "not_ids"       => array($num_appId),
        );
        $this->appRows = $this->mdl_app->mdl_list(100, 0, $_arr_search);
    }


    private function user_check() {
        $this->userInput = $this->mdl_user_api->input_token("post");

        if ($this->userInput["rcode"] != "ok") {
            $this->obj_api->show_result($this->userInput);
        }

        $this->userRow = $this->mdl_user->mdl_read($this->userInput["user_str"], $this->userInput["user_by"]);
        if ($this->userRow["rcode"] != "y010102") {
            $this->obj_api->show_result($this->userRow);
        }

        if ($this->userRow["user_status"] == "disable") {
            $_arr_return = array(
                "rcode" => "x010401",
            );
            $this->obj_api->show_result($_arr_return);
        }

        if ($this->userRow["user_access_expire"] < time()) {
            $_arr_return = array(
                "rcode" => "x010231",
            );
            $this->obj_api->show_result($_arr_return);
        }

        /*print_r($this->userInput);
        print_r("<br>");
        print_r($this->userRow);*/

        if ($this->userInput["user_access_token"] != md5(fn_baigoCrypt($this->userRow["user_access_token"], $this->userRow["user_name"]))) {
            $_arr_return = array(
                "rcode" => "x010230",
            );
            $this->obj_api->show_result($_arr_return);
        }

        unset($this->userRow["user_pass"], $this->userRow["user_nick"], $this->userRow["user_note"], $this->userRow["user_status"], $this->userRow["user_time"], $this->userRow["user_time_login"], $this->userRow["user_ip"]);
    }
}
