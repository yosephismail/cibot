<?php
    /**
     * versi                    :   1.0.0
     * Page Type                :   Helper;
     * File Name                :   cibottext_helper.php;
     * Path File                :   APPPATH.'helpers\cibottext_helper.php';
     * Create Date              :   unKnown;
     * Programmer               :   Eka Rafi Dimasyono;
     * Last Update              :   20/10/2015;
     * Last Programmer Update   :   Yoseph;
     * Description Update       :
     *  - Buat Baru (yi)   
    */
defined('BASEPATH') OR exit('No direct script access allowed');

if ( ! function_exists('word_filter'))
{
    function word_filter($text="",$filtrd="lcase ucase num space symbol enter tab",$excld="",$returntext = TRUE)
    {
        $filter="";
        $arrfilter = explode(" ", $filtrd);
        if(count($arrfilter))
        {
            $i = 0;
            while($i<count($arrfilter))
            {
                switch ($arrfilter[$i])
                {
                    case "ucase" :$filter .= "ABCDEFGHIJKLMNOPQRSTUVWXYZ"; break;
                    case "lcase" :$filter .= "abcdefghijklmnopqrstuvwxyz"; break;
                    case "num" :$filter .= "0123456789-."; break;
                    case "symbol" :$filter .= "!@#$%^&*()_+=<>[]{}:;,./?'".'"'; break;
                    case "space" : $filter .= " "; break;
                    case "enter" : $filter .= chr(13).chr(10);break;
                    case "tab" : $filter .= "   ";break;
                    default : $filter .= $arrfilter[$i];break;
                }
                $i++;
            }
        }
        if(strlen($excld)>0)
        {
            $i=0;
            while($i <  strlen($excld))
            {
                $filter = str_replace($excld[$i], "", $filter);
                $i++;
            }
        }
        $vald = FALSE;
            if(strlen($text)>0)
            {
                $i=0;
                while($i< strlen($text))
                {
                    if (strpos($filter, $text[$i]) === false)
                    {
                        if($returntext == TRUE)
                        {
                            $text = str_replace($text[$i], "", $text);
                        }
                        else if($returntext == FALSE)
                        {
                            $vald = FALSE;
                            break;
                        }
                    }
                    else
                    {
                        if($returntext == FALSE)
                        {
                            $vald = TRUE;
                        }
                        $i++;
                    }
                }
            }
        $text = ($returntext == FALSE)?$vald:$text;    
        return $text;
    }
    
}