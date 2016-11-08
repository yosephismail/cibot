<?php

require_once('/helper/cibottext_helper.php');

class Cibot_Core extends CI_Controller
{
    /**
     * versi                    :   1.0.0
     * Page Type                :   Class;
     * Class Name               :   Cibot_Core;
     * File Name                :   cibot_core.php;
     * Path File                :   APPPATH.'core\cibot_core.php';
     * Create Date              :   07/08/2015;
     * Programmer               :   Yoseph;
     * Last Update              :   19/10/2015;
     * Last Programmer Update   :   Yoseph;
     * Description Update       :
     *  - Buat Baru (yi)   
     *  - Penambahan getUrlFunction
     */
    protected $cibotConfig;
    protected $listmenu;
    protected $apiserverconfig;
    
    function __construct() {
        parent::__construct();
        $this->load->library('rest');
        $this->load->library('session');
        $this->load->helper('file');
        $this->load->helper('url');
        $this->cibotConfig = $this->loadCibotConfig();
        $userlevel = $this->session->userdata('userlevel');
        $userid = $this->session->userdata('userid');
        $this->setSession($userlevel,$userid);
        $this->initRestServer();
        $this->listmenu = $this->setListMenu();
        $this->pathURI();
        $this->setTimeZone();
    }
    
    public static function init() {
        global $Cibot;
        $Cibot = new Cibot_Core();
        $Cibot->loadApp();
    }
    private function loadCibotConfig()
    {
        $this->load->config('Webapp_config');
        $arryini = $this->getDataIniConfigFile($this->config->item('web_id'));
        $config = array(
            "web_id"=> $this->config->item('web_id'),
            "session_name"=> $this->config->item('session_name'),
            "errorpathimage" => $arryini->mediaserver->patherrorimage."noimageavailable.jpg",
            "uploadpathimage" => $arryini->mediaserver->pathimghupload,
            "viewpathimage" => $arryini->mediaserver->pathviewimage,
            "attpathimage" => $arryini->mediaserver->pathattrimage,
            "api_url" => $arryini->restserver->api_url,
            "time_zone" => $arryini->zone->time_zone,
            "rest_key_name" => $arryini->restserver->rest_key_name,
            "pathiconimage" => $arryini->mediaserver->pathiconimage,
            "pathlogoimage" => $arryini->mediaserver->pathlogoimage,
            "urlmedia" => $arryini->mediaserver->media_url,
            "pathmedia" => $arryini->mediaserver->pathmedia,
            "pathuserimage" => $arryini->mediaserver->pathuserimage,
            "smtp_host" => $this->config->item('smtp_host'),
            "smtp_port" => $this->config->item('smtp_port'),
            "smtp_user" => $this->config->item('smtp_user'),
            "smtp_pass" => $this->config->item('smtp_pass'),
            "ftp_host" => $arryini->ftpmediaserver->ftp_host,
            "ftp_user" => $arryini->ftpmediaserver->ftp_user,
            "ftp_pass" => $arryini->ftpmediaserver->ftp_pass,
            "ftp_port" => $arryini->ftpmediaserver->ftp_port
        );
        return $config;
    }
    public function setSession($userlvl="guest",$userid="guest",$stat="")
    {
        global $Cibot;
        $userlvl = empty($userlvl)?"guest":$userlvl;
        $userid = empty($userid)?"guest":$userid;
        $userlvlsess = $this->session->userdata('userlevel');
        $useridsess = $this->session->userdata('userid');
        $webid = $this->session->userdata('web_id');
        $apictrlsess = $this->session->userdata('apicontroller');
        $userlvl = (empty($userlvlsess)||($userlvlsess == 'guest'))?$userlvl:$userlvlsess;//user level login user if not guest
        $userid = (empty($useridsess)||($useridsess == 'guest'))?$userid:$useridsess;//user login if not gues
        $apicontroller = (empty($apictrlsess))?"api/Guestapi":$apictrlsess; //controller login if not guest
        $this->load->config('config');
        $this->config->set_item('sess_cookie_name',$this->cibotConfig['session_name']);
        $apikey="";
        if(empty($this->session->userdata['userlevel']))
        {
            $apikey = md5("guestcibotprojectapi");
            $apicontroller = "api/Guestapi";
            $cibotSession = array
            (
                "userlevel" => $userlvl,
                "web_id" => $this->cibotConfig['web_id'],
                "api_url" => $this->cibotConfig['api_url'],
                "apikeyname" => $this->cibotConfig['rest_key_name'],
                "userid" => $userid,
                "apikey" => $apikey,
                "apicontroller" =>$apicontroller
            );
            $this->session->set_userdata($cibotSession);
            $url = $this->getUrlFunction('index','init');
            redirect($url);
        }
        if($userlvl<>'guest')
        {
            $apictrl = $webid ."_api/".strtoupper($userlvl[0]);
            $i=1;
            while($i<strlen($userlvl))
            {
                $apictrl .= $userlvl[$i];
                $i++;
            }
            $apictrl .= "api";//new apicontroller non guest login user
            $iskey = $this->APIClient("GET","cekkey",NULL,$userlvl,$apictrl);
            $newkey = md5($userlvl."_".$webid."api".date("d-m-Y"));
            if($stat == "login")
            {
                $content = array(
                    "userlevel" => $userlvl,
                    "key" => $newkey,
                    "level" => 1,
                    "date_created" => date("d-m-Y"),
                    "controller" => $apictrl
                );
                if($iskey<>'')
                {               
                    //update key with new key when key is exist in apikey
                    $this->APIClient("PUT","upkey",$content,$userlvl,$apicontroller);
                    $iskey = $this->APIClient("GET","cekkey",NULL,$userlvl,$apicontroller);           
                    $isi = array("apikeyid"=>$iskey->id);
                    //check whether key in apiaccess is the same key with key in apikey
                    $iskeyaccess = $this->APIClient("GET","cekkeyaccess",$isi,"",$apicontroller);
                    if(count($iskeyaccess) > 0)
                    {
                        //update Key when is exist in apiaccess
                        $valuekey = array(
                            "key"=>$iskey->key,
                            "date_modified"=>date("d-m-Y")
                            );
                        $apiaccessID = $iskeyaccess[0]->apikeyid;
                        $this->APIClient("PUT","upkeyaccess",$valuekey,$apiaccessID,$apicontroller);
                    }
                    $userlvl = $iskey->userlevel;
                    $apikey = $iskey->key;
                    $apicontroller = $iskey->controller;
                }
            }
            else 
            {              
               //with same userlevel no guest but reload page on set session
               $apicontroller = $this->session->userdata('apicontroller'); 
               $userid = $this->session->userdata('userid');
               $apikey = $this->session->userdata('apikey');
               $userlvl = $this->session->userdata('userlevel');
            }
        }
        else 
        {
            $apikey = md5("guestcibotprojectapi");
            $apicontroller = "api/Guestapi";
        }
        $cibotSession = array
        (
            "userlevel" => $userlvl,
            "web_id" => $this->cibotConfig['web_id'],
            "api_url" => $this->cibotConfig['api_url'],
            "apikeyname" => $this->cibotConfig['rest_key_name'],
            "userid" => $userid,
            "apikey" => $apikey,
            "apicontroller" =>$apicontroller
        );
        $this->session->set_userdata($cibotSession);
        $this->initRestServer($apikey);
        $this->listmenu = $this->setListMenu();
    }

    public function initRestServer($key="")
    {
        $apikey = ($key<>"")?$key:$this->session->userdata('apikey');
        $this->apiserverconfig = array('server' => $this->session->userdata('api_url'),
                        'api_key'		=> $apikey,
                        'api_name'		=> $this->session->userdata('apikeyname'),
                        //'http_user' 		=> 'username',
                        //'http_pass' 		=> 'password',
                        //'http_auth' 		=> 'basic',
                        'ssl_verify_peer' => FALSE,
                        'ssl_cainfo' 		=> '/certs/cert.pem'
                        );
        $this->rest->initialize($this->apiserverconfig);
        $content = array (
            "id" => $this->cibotConfig['web_id']
        );
        $initserver = $this->rest->get('admin_api/Cibot_uripath/statusserver',$content);
    }

    public function loadApp()
    {
        $content = array (
            "webid" => $this->session->userdata('web_id')
        );
        $appinfo = $this->APIClient("GET","appinfo",$content);
        $logodir = str_replace('./','',$this->cibotConfig['pathlogoimage']);
        $icondir = str_replace('./','',$this->cibotConfig['pathiconimage']);
        $arrlogo = unserialize($appinfo->applogoname);
        $urlmedia = $this->cibotConfig['urlmedia'];
        $arrpathlogo = array();
        if(count($arrlogo)>0)
        {
            $i=0;
            while($i<count($arrlogo))
            {
                $arrpathlogo["logo".($i+1)] = $urlmedia.$logodir.$arrlogo[$i];
                $i += 1;
            }
        }
        $pathiconimage = $urlmedia.$icondir.$appinfo->appiconname;
        $addappsession = array
        (
            "iconapp" => $pathiconimage,
            "titleapp" => $appinfo->appwebname,
            "logoapp" => $arrpathlogo
        );
        $this->session->set_userdata($addappsession); 
        if(count($appinfo)>0)
        {
            $aktif = $appinfo->appavailable;
            if($aktif == 'f')
            {
                redirect(base_url().'error/pagenotfound');                
            }
            else 
            {
                $this->pageRedirect();
            }
        }
        else 
        {
            redirect(base_url().'error/pagenotfound');
        }
    }
    

    private function setTimeZone()
    {
        date_default_timezone_set($this->cibotConfig['time_zone']);                
    }
    

    public function pageRedirect()
    {
        $content = array (
            "levelid" => $this->session->userdata('userlevel'),
            "webid" => $this->session->userdata('web_id')
        );
        $contevent = array (
            "webid" => $this->session->userdata('web_id')
        );
        $eventredirect = $this->APIClient("GET","eventredirect",$contevent);
        $redirectpage = $this->APIClient("GET","pageredirect",$content);
        if(($eventredirect<>"")&&(count($eventredirect)>0))
        {
            redirect(base_url().trim($eventredirect->aplpagename));            
        }
        else
        if(count($redirectpage)>0)
        {            
            redirect(base_url().trim($redirectpage->aplpagename));            
        }
    }
    
    private function pathURI()
    {
        $content = array (
            "webid" => $this->session->userdata('web_id')
        );
        $listPath = $this->APIClient("GET","listpath",$content);
        if(file_exists(APPPATH . "cache/routes.php"))
        {
            $myfile = fopen(APPPATH . "cache/routes.php", "r") or die("Tidak dapat membuka File");
            $stringtext = fread($myfile, filesize(APPPATH . "cache/routes.php"));
            $arrstr = explode(";", $stringtext);
            fclose($myfile);
            $linetext = array();
            if(count($arrstr)>0)
            {
                $i=1;
                while($i < count($arrstr))
                {
                    if(trim($arrstr[$i])<>"")
                    {
                        array_push($linetext, trim($arrstr[$i]).";");
                    }
                    $i += 1;
                }
            }
        }
        if(count($listPath)>0)
        {
            $i=0;
            $listfromrec = array();
            while($i < count($listPath))
            {               
                $pagename = trim($listPath[$i]->aplpagename);
                $controlname = trim($listPath[$i]->aplpagecontroller);
                $output = '$route["'.$pagename.'"] = "'.$controlname.'";';
                if(array_search($output, $listfromrec) == false)
                {
                    array_push($listfromrec, $output);
                }    
                $k= 0;
                if(!empty($listPath[$i]->aflfunctionid))
                {
                    if($listPath[$i]->aflfunctionname<>"index")
                    {
                        $pagename .= '/'.$listPath[$i]->aflfunctionname;
                    }
                    $controlname .= '/'.$listPath[$i]->aflfunctionname;
                    $outputwf = '$route["'.$pagename.'"] = "'.$controlname.'";';
                    array_push($listfromrec, $outputwf);
                }
                if(!empty($listPath[$i]->aflparameter))
                {
                    $arrparam = unserialize($listPath[$i]->aflparameter);
                    if(count($arrparam) > 0)
                    {
                        $j=0;
                        while($j < count($arrparam))
                        {
                            $k += 1;
                            $pagename .= '/(:any)';
                            $controlname .= '/$'.$k;                    
                            $j += 1;
                        }
                    }        
                    $outputwf = '$route["'.$pagename.'"] = "'.$controlname.'";';
                    array_push($listfromrec, $outputwf);
                }
                $i += 1;
            }
            $pathdiff1 = array_diff($listfromrec,$linetext);
            $pathdiff2 = array_diff($linetext,$listfromrec);
            if((count($pathdiff1)>0)||(count($pathdiff2)>0))
            {
                $header = '<?php defined("BASEPATH") OR exit("No direct script access allowed");';
                write_file(APPPATH . "cache/routes.php", $header."\r\n");
                $i = 0;
                while($i < count($listfromrec))
                {
                    write_file(APPPATH . "cache/routes.php",$listfromrec[$i]."\r\n",'a');
                    $i += 1;
                }
            }
        }
    }
    
    private function setListMenu()
    {
        $content = array (
            "webid" => $this->session->userdata('web_id'),
            "levelid" => $this->session->userdata('userlevel')
        );
        $listpage = $this->APIClient("GET","listmenu",$content);        
        return $listpage;
    }
    
    public function getDataPanel($panelid = "",$webid="")
    {
        $content = array (
            "panelname"=>$panelid,
            "panelwebid" => $webid
        );
        $datapanel = $this->APIClient("GET","listpanel",$content);
        return $datapanel;
    }
    public function getChildDataPanel($id = "",$webid="")
    {
        $content = array (
            "panelparentpageid"=>$id,
            "webid" => $webid
        );
        $datapanel = $this->APIClient("GET","listpanel",$content);
        return $datapanel;
    }

    public function getListMenu()
    {
        return $this->listmenu;
    }
    
    public function showBreadCrumb($activepage="",$activefunction="")
    {
        $listmenu = $this->listmenu;
        $content = array (
            "webid" => $this->session->userdata('web_id'),
            "pagename" => $activepage
        );
        $dataMenuActive = $this->APIClient("GET","listpath",$content);
        $html = "<ol class='breadcrumb'>"; 
        if(count($listmenu)>0)
        {
            $i=0;
            while($i < count($listmenu))
            {
                if($dataMenuActive[0]->panelparentpageid == $listmenu[$i]->aplpageid)
                {
                    $parent = "<li><a href='#'>".$listmenu[$i]->aplpagetitle."</a></li>";
                }
                if($dataMenuActive[0]->aplpageid == $listmenu[$i]->aplpageid)
                {
                    $child= "<li class='active'><a href='".$listmenu[$i]->aplpagename."'>".$listmenu[$i]->aplpagetitle."</a></li>";
                    if(!empty($activefunction))
                    {
                        $child= "<li><a href='".$this->getUrlFunction('index',$listmenu[$i]->aplpagename)."'>".$listmenu[$i]->aplpagetitle."</a></li>";
                        $function= "<li class='active'><a href='".$this->getUrlFunction($activefunction,$listmenu[$i]->aplpagename)."'>".$activefunction."</a></li>";                        
                    }
                }
                $i++;
            }            
        }
        $html .= (empty($parent)?"":$parent).(empty($child)?"":$child).(empty($function)?"":$function);
        $html .= "</ol>";
        return $html;
    }
    
    public function showPanelMenu($panelid="",$activepage="")
    {
       $listmenu = $this->listmenu;
       if(!empty($activepage))
       {
        $content = array (
            "webid" => $this->session->userdata('web_id'),
            "pagename" => $activepage
        );
        $dataMenuActive = $this->APIClient("GET","listpath",$content);
       } 
        $web_id = $this->session->userdata('web_id');
       $infopanel = $this->getDataPanel($panelid,$web_id);
       if($infopanel[0]->panelanotherparam<>NULL)
       {
           $role = $infopanel[0]->panelanotherparam;
       }
       else
       {
           $role = "";
       }
       $navcollapse = "<ul class='".$infopanel[0]->panellistclass."' ".$role.">";
       $htmlcode = $navcollapse;
       if(count($listmenu)>0)
       {
            $i = 0;
            while($i < count($listmenu))
            {   
                if(trim($listmenu[$i]->panelname) == $panelid)
                {
                    $icon = ((trim($listmenu[$i]->aplpageicon)<>'')&&(trim($listmenu[$i]->aplpageicon)<>NULL))?"<i class='".trim($listmenu[$i]->aplpageicon)."'></i>":"";
                    $backicon = ((trim($listmenu[$i]->aplpagebackicon)<>'')&&(trim($listmenu[$i]->aplpagebackicon)<>NULL))?"<i class='".trim($listmenu[$i]->aplpagebackicon)."'></i>":"";
                    $posicon = ($icon."&nbsp;&nbsp;&nbsp;".(($listmenu[$i]->apltitlehide<>1)?trim($listmenu[$i]->aplpagetitle):'')."&nbsp;".$backicon);
                    $active = (!empty($dataMenuActive))?((($listmenu[$i]->aplpageid == $dataMenuActive[0]->aplpageid)||($listmenu[$i]->aplpageid == $dataMenuActive[0]->panelparentpageid))?"active":""):"";
                    $htmlcode .= "<li class = '".(!empty($listmenu[$i]->apllistitemclass)?($listmenu[$i]->apllistitemclass):"")." ".$active."'>";                        
                    if(!empty($listmenu[$i]->aplpagecontroller))
                    {
                        if($listmenu[$i]->aplpagecontroller == "#")
                        {
                            $htmlcode .= "<a href='#'>".$posicon."</a>";                            
                        }
                        else 
                        {
                            $htmlcode .= "<a href='".$this->getUrlFunction("index",$listmenu[$i]->aplpagename)."'>".$posicon."</a>";
                        }
                    }
                    else
                    {
                        $htmlcode .= $posicon;
                    }
                    $infochildpanel = $this->getChildDataPanel($listmenu[$i]->aplpageid);
                    if(count($infochildpanel)>0)
                    {
                        $htmlcode .= $this->showPanelMenu($infochildpanel[0]->panelname,$activepage)."</li>";
                    }
                    else
                    {
                        $htmlcode .= "</li>";                        
                    }
                }
                $i += 1;
            }       
        }
        $htmlcode .= "</ul>";
       return $htmlcode;
    }
    public function getPathImageURL($groupid="",$imagename="")
    {
        $pathmedia = $this->cibotConfig['pathmedia'];
        $errorpath = $this->cibotConfig['errorpathimage'];
        $uploadpath = $this->cibotConfig['uploadpathimage'].$groupid."\\";
        $viewpath = $this->cibotConfig['viewpathimage'].$groupid."/";
        $urlmedia = $this->cibotConfig['urlmedia'];            
        $dirview = $pathmedia.str_replace("./", "", $viewpath);
        if(!file_exists($dirview))
        {
            mkdir($dirview);
            if(file_exists($dirview."index.html")==FALSE)
            {
                $this->createForbidenFile($dirview);
            }
        }
        else
        {
            if(file_exists($dirview."index.html")==FALSE)
            {
                $this->createForbidenFile($dirview);
            }
        }       
        $uploadpathwf = str_replace("\\", "/",$pathmedia.str_replace("./", "",$uploadpath).$imagename);
        $viewpathwf = $pathmedia.str_replace("./", "",$viewpath).str_replace("\\", "/", $imagename);
        if((file_exists($uploadpathwf)==TRUE))
        {
            copy($uploadpathwf,$viewpathwf);
            $httpimage = $urlmedia.str_replace("./", "", $viewpath).str_replace("\\", "/", $imagename); 
        }
        else if((file_exists($uploadpathwf)==FALSE)&&(file_exists($viewpathwf)==TRUE))
        {
            $httpimage = $urlmedia.str_replace("./", "", $viewpath).str_replace("\\", "/", $imagename);             
        }
        else
        {
            $httpimage = $urlmedia.str_replace("./", "", $errorpath); 
        }            
        return $httpimage;            
    }

    public function getPathUserImageURL($groupid="",$imagename="")
    {
        $pathmedia = $this->cibotConfig['pathmedia'];
        $errorpath = $this->cibotConfig['errorpathimage'];
        $uploadpath = $this->cibotConfig['uploadpathimage'].$groupid."\\";        
        $userpath = $this->cibotConfig['pathuserimage'].$groupid."/";        
        $urlmedia = $this->cibotConfig['urlmedia'];
        $dirview = $pathmedia.str_replace("./", "", $userpath);
        if(!file_exists($dirview))
        {
            mkdir($dirview);
            if(file_exists($dirview."index.html")==FALSE)
            {
                $this->createForbidenFile($dirview);
            }
        }
        else
        {
            if(file_exists($dirview."index.html")==FALSE)
            {
                $this->createForbidenFile($dirview);
            }
        }       
        if(!empty($imagename))
        {
            $uploadpathwf = str_replace("\\", "/",$pathmedia.str_replace("./", "",$uploadpath).$imagename);
            $viewpathwf = $pathmedia.str_replace("./", "",$userpath).str_replace("\\", "/", $imagename);
            if((file_exists($uploadpathwf)==TRUE))
            {
                copy($uploadpathwf,$viewpathwf);
                $httpimage = $urlmedia.str_replace("./", "", $userpath).str_replace("\\", "/", $imagename); 
            }
            else if((file_exists($uploadpathwf)==FALSE)&&(file_exists($viewpathwf)==TRUE))
            {
                $httpimage = $urlmedia.str_replace("./", "", $userpath).str_replace("\\", "/", $imagename);             
            }
            else
            {
                $httpimage = $urlmedia.str_replace("./", "", $errorpath); 
            }            
        }
        else
        {
            $httpimage = $urlmedia.str_replace("./", "", $errorpath); 
        }
            
        return $httpimage;            
    }
    
    public function getAtributImage($attribut="")
    {
        $content = array (
            "attributnama" => $attribut
        );
        $dataAttribut = $this->APIClient("GET","attribut",$content);
        $attpath = $this->cibotConfig['attpathimage']; 
        $httpimage = base_url().str_replace("./", "", $attpath.$dataAttribut[0]->atiattributimage); 
        return $httpimage;
    }
    
    private function createForbidenFile($pathdir="")
    {
        $content = '<!DOCTYPE html>
        <html>
        <head>
                <title>403 Forbidden</title>
        </head>
        <body>

        <p>Directory access is forbidden.</p>

        </body>
        </html>
        ';
        write_file($pathdir."index.html", $content."\r\n");        
    }
    private function createHtAccessForbidenFile($pathdir="")
    {
        $content = '<IfModule authz_core_module>
	Require all denied
        </IfModule>
        <IfModule !authz_core_module>
                Deny from all
        </IfModule>
        ';
        write_file($pathdir.".htaccess", $content."\r\n");        
    }
    public function get3rdServerName($jnsserver="")
    {
        if($jnsserver == "media")
        {
            $httpaddres = $this->cibotConfig['urlmedia'];
        }
        return $httpaddres;
    }
    public function getUrlFunction($funct="",$page="",$param=NULL)
    {
        $listmenu = $this->listmenu;
        $pathurl = "";
        if($page=="")
        {
            $pgname = $this->uri->segment(1);            
        }
        else 
        {
            
            $pgname = $page;                        
        }
        if(count($listmenu)>0)
        {
            $i=0;
            $priviledge = "";
            $controller = "";
            $ketemu = FALSE;
            while(($i < count($listmenu))&&($ketemu <> TRUE))
            {
                if($listmenu[$i]->aplpagename == $pgname)
                {
                    $ketemu = TRUE;
                    $priviledge = $listmenu[$i]->appprivileges;
                    $controller = $listmenu[$i]->aplpagecontroller;
                }
                $i += 1;
            }
            if($priviledge<>NULL)
            {
                $arrprev = unserialize($priviledge);
                $arrhaystack = array();
                if(count($arrprev)>0)
                {
                    $i=0;
                    while($i < count($arrprev))
                    {
                        $arrhaystack[$arrprev[$i]] = $arrprev[$i];
                        $i += 1;
                    }
                }
                $exist = array_search($funct, $arrhaystack);
                if($exist <> FALSE)
                {
                    if(($page=="")&&($funct==""))
                    {
                        $pathurl = current_url()."/index";
                    }
                    else if(($page=="")&&($funct<>""))
                    {
                        $pathurl = current_url()."/".$funct;
                    }
                    else if(($page<>"")&&($funct<>""))
                    {
                        if($funct<>"index")
                        {
                            $pathurl = base_url().$pgname."/".$funct;
                        }
                        else if($funct=="index")
                        {                            
                            $pathurl = base_url().$pgname;
                        }
                    }
                    else if(($page<>"")&&($funct==""))
                    {
                        $pathurl = base_url().$pgname;
                    }
                }
                else if($exist == FALSE)
                {
                    $pathurl = base_url()."error/forbidenpage";
               }                
            }
            else
            {
                $pathurl = base_url()."error/forbidenpage";                
            }                           
        }            
        if(!empty($param))
        {
            $stringparam = json_encode($param);
            $stringparam = str_replace("{", "", $stringparam); 
            $stringparam = str_replace("}", "", $stringparam);
            $arrparam = explode(",", $stringparam);
            $arrobject = array();
            if(count($arrparam)>0)
            {
                $i=0;
                while($i < count($arrparam))
                {
                    $arritemparam = explode(":", $arrparam[$i]);
                    if(count($arritemparam)>0)
                    {
                        $paramobj = new stdClass();
                        $paramobj->paramname = str_replace('"', "", $arritemparam[0]);
                        $paramobj->paramvalue = str_replace('"', "", $arritemparam[1]);
                        array_push($arrobject, $paramobj);
                    }
                    $i++;
                }
            }
            if(count($arrobject)>0)
            {
                $i=0;
                while($i < count($arrobject))
                {
                    $pathurl .= "/".$arrobject[$i]->paramvalue;
                    $i++;
                }
            }
        }
        return  $pathurl;        
    }
    
    public function APIClient($action="",$modelid="",$content=NULL,$id="",$controller="")
    {
        if(empty($controller))
        {
            $apicontroller = $this->session->userdata('apicontroller');
        }
        else 
        {
            $apicontroller = $controller;
        }
        if(!empty($id))
        {
            $filterid = "/id/".$id;
        }
        else
        {
            $filterid = "";
        }

        if($action == "GET")
        {
            if(!empty($content))
            {
                $returnValue = $this->rest->get($apicontroller.'/list/modelid/'.$modelid.$filterid,$content);
            }
            else 
            {
                $returnValue = $this->rest->get($apicontroller.'/list/modelid/'.$modelid.$filterid);                
            }
        }
        else if($action == "POST")
        {
            if(!empty($content))
            {
                $returnValue = $this->rest->post($apicontroller.'/insert/modelid/'.$modelid,$content);
            }
            else 
            {
                $returnValue = $this->rest->post($apicontroller.'/insert/modelid/'.$modelid);                
            }            
        }
        else if($action == "PUT")
        {
            if(!empty($content))
            {
                $returnValue = $this->rest->put($apicontroller.'/update/modelid/'.$modelid.$filterid,$content);
            }
        }
        return $returnValue;
    }
    
    public function setValidatedEmail($email)
    {
        $temail = word_filter($email, "lcase ucase num space symbol", "!#$%^&*()_+=<>[]{}:;,/?'".'"');
        $temail = str_replace("@", "[at]", $temail);
        $temail = str_replace(".", "[dot]", $temail); 
        return $temail;
    }

    public function setReversedEmail($email)
    {
        $temail = str_replace("[at]","@",  $email);
        $temail = str_replace("[dot]",".",  $temail); 
        return $temail;
    }
    
    public function setValidatedPassword($password)
    {
        $tpassword = word_filter($password, "lcase ucase num symbol");
        $tpassword = ($tpassword<>'')?md5($tpassword):'';
        return $tpassword;
    }
    
    public function cekValidInput($input,$filter,$exclude)
    {
        $cekvalid = word_filter($input,$filter,$exclude,FALSE);
        return $cekvalid;
    }
    
    public function cibotMDIParentView($fileheader,$filefooter,$var)
    {
        $this->cibotMDIHeaderFile = $fileheader;
        $this->cibotMDIFooterFile = $filefooter;
        $this->var_index = $var;
    }
    public function cibotView($filename="",$var=NULL)
    {
        if(isset($var))
        {
            if(isset($this->var_index))
            {
                $arrayvar = array_merge($this->var_index,$var);
            }
            else 
            {
                $arrayvar = $var;                
            }
        }
        else
        {
            $arrayvar = $this->var_index;
        }       
        if(!empty($this->cibotMDIHeaderFile))
        {
            $this->load->view($this->cibotMDIHeaderFile,$arrayvar);
        } 
        if($filename <> "")
        {
            $this->load->view($filename,$arrayvar);
        }    
        if(!empty($this->cibotMDIFooterFile))
        {
            $this->load->view($this->cibotMDIFooterFile);            
        }
    }
    
    private function readIniConfigFile($filename,$groupid="")
    {
        $arrayinidata = parse_ini_file($filename,true);
        $stringinidata = str_replace(":server",$_SERVER['DOCUMENT_ROOT'],str_replace(":webid",$groupid, json_encode($arrayinidata)));
        $arrayinidata = json_decode($stringinidata);
        return $arrayinidata;
    }
    
    public function getDataIniConfigFile($groupid="")
    {
        return $this->readIniConfigFile("/config/Cibot_config.ini",$groupid);
    }
    
    public function sendMail($mailto,$subject,$message="")
    {
        $config = Array(
            'protocol' => 'smtp',
            'smtp_host' => $this->cibotConfig['smtp_host'],
            'smtp_port' => $this->cibotConfig['smtp_port'],
            'smtp_user' => $this->cibotConfig['smtp_user'], 
            'smtp_pass' => $this->cibotConfig['smtp_pass'],
            'mailtype' => 'html',
            'charset' => 'iso-8859-1',
            'wordwrap' => TRUE
        );

        $this->load->library('email', $config);
        $this->email->set_newline("\r\n");
        $this->email->from($this->cibotConfig['smtp_user']);
        $this->email->to($mailto);
        $this->email->subject($subject);
        $this->email->message($message);
        if($this->email->send())
        {
            echo 'Email sent.';
        }
        else
        {
            show_error($this->email->print_debugger());
        }        
    }
    
    public function showNotification($notification,$typenotif="info")
    {
        $html="";
        if(!empty($notification))
        {
            switch ($typenotif)
            {
                case "info" :
                    $caption = "Informasi";
                    $icon = "fa-info";
                    break;
                case "danger" :
                    $caption = "Perhatian!";
                    $icon = "fa-ban";
                    break;
                case "warning" :
                    $caption = "Hati-hati!";
                    $icon = "fa-warning";
                    break;
                case "success" :
                    $caption = "Sukses";
                    $icon = "fa-check";
                    break;
            }
            $html = "<div class='row'>
                    <div class='col-md-12'>
                        <div class='alert alert-".$typenotif."'>
                            <h4>
                                <button class='close' type='button' data-dismiss='alert' aria-hidden='true'>x</button>
                                <i class='icon fa fa-ban'></i>
                                ".$caption."
                            </h4>
                            ".$notification."
                        </div>
                    </div>
                </div>
            ";
        }
        return $html;
    }
    
    public function uploadftp($tokenrcv,$webappid,$nmfile="",$nmfile_upload="",$subfolder="") 
    {

        $token = md5("tokenftp".$this->session->userdata('web_id').$this->session->userdata('userid').date("Y-m-d"));
        if($tokenrcv == $token)
        {
            $configftp = $this->getDataIniConfigFile($webappid);

            $ftp_host = $configftp->ftpmediaserver->ftp_host;
            $ftp_user = $configftp->ftpmediaserver->ftp_user;
            $ftp_pass = $configftp->ftpmediaserver->ftp_pass;
            $ftp_folder = str_replace(".","",$configftp->mediaserver->pathimghupload);
            $ftp_port = $configftp->ftpmediaserver->ftp_port;
            
            $conn_id = ftp_connect($ftp_host, $ftp_port);
            $login_result = ftp_login($conn_id, $ftp_user, $ftp_pass);
            $subfolder = $subfolder."/";
            if ((!$conn_id) || (!$login_result)) {
                $result = "gagal koneksi";
            } else {
                if (!@ftp_chdir($conn_id, $ftp_folder.$subfolder)) {
                    if (!@ftp_mkdir($conn_id, $ftp_folder.$subfolder)) {
                        $result = "gagal buat direktori";
                    } 
                    if(@ftp_chdir($conn_id, $ftp_folder.$subfolder));
                }
                if(empty($result)) {
                    //upload filenya
                    if (empty($result)) {
                        if (!@ftp_put($conn_id,$nmfile, $nmfile_upload , FTP_BINARY)) {
                            $result = "gagal upload";
                        }
                    }
                }
                ftp_close($conn_id);
            }
            
        }
        else
        {
            $result = "Maaf Anda Tidak Memiliki Tiket Akses ke FTP";
        }
        return $result;
    }

    function deleteftp($tokenrcv,$nmfile_upload="",$subfolder="") 
    {

        $token = md5("tokenftp".$this->session->userdata('web_id').$this->session->userdata('userid').date("Y-m-d"));
        if($tokenrcv == $token)
        {
            $configftp = $this->cibotConfig;

            $ftp_host = $configftp['ftp_host'];
            $ftp_user = $configftp['ftp_user'];
            $ftp_pass = $configftp['ftp_pass'];
            $ftp_folder = $configftp['ftp_folder'];
            $ftp_port = $configftp['ftp_port'];

            $conn_id = ftp_connect($ftp_host, $ftp_port);
            $login_result = ftp_login($conn_id, $ftp_user, $ftp_pass);
            if ((!$conn_id) || (!$login_result)) {
                $result = "gagal koneksi";
            } else {
                if (!@ftp_chdir($conn_id, $ftp_folder.$subfolder )) {
                    $result = "gagal folder tidak ada";                
                }
                if(empty($result)) {
                    ftp_pasv($conn_id, true); //for passive
                    if (!@ftp_delete($conn_id, $nmfile_upload)) {
                        $result = "gagal hapus";
                    }
                    else
                    {
                        $result = "Berhasil Hapus";
                    }
                }
                ftp_close($conn_id);
            }
        }
        else
        {
            $result = "Maaf Anda Tidak Memiliki Tiket Akses ke FTP";
        }
        return $result;
    }
    
    public function uploadFile($token,$webappid,$uploaddir,$subfolder,$attachmentfile,$filename,$filetype="")
    {
        if(($attachmentfile['error']==0)&&(!empty($attachmentfile)))
        {
            $uploadOk = 1;
            if(file_exists($uploaddir))
            {
                $ext = pathinfo(basename($attachmentfile["name"]),PATHINFO_EXTENSION);
                
                if(!empty($filetype))
                {
                    $arrtype = explode(" ", $filetype);
                    
                    $stringtype = implode("/", $arrtype);
                    if(count(array_keys($arrtype,$ext)) == 0)
                    {
                        $error =  "Maaf, Hanya file ".$stringtype." yang diperbolehkan.";
                        $uploadOk = 0;                        
                    }
                }
                                
                if ($attachmentfile["size"] > 5000000) 
                {
                    $error = "Maaf, Ukuran File tidak Boleh Melebihi 5MB.";
                    $uploadOk = 0;
                }                                            
                if ($uploadOk == 0) 
                {
                    $error .= ", File Tidak Bisa Diupload.";
                } 
                else 
                {
                    $uploadfile = $uploaddir."/".$attachmentfile["name"];
                    if (move_uploaded_file($attachmentfile["tmp_name"], $uploadfile)) 
                    {
                        $resultupload = $this->uploadftp($token,$webappid,$filename, $uploadfile, $subfolder);
                        if(!empty($resultupload))
                        {
                            $error = $resultupload;
                        }
                        else
                        {
                            unlink($uploadfile);
                        }
                    } 
                    else 
                    {
                        $error = "Maaf, Ada Kesalahan (Error) ketika mengupload File.";
                    }
                }                        
            }                    
        }        
        return $error;
    }
}
