<?php
if(!defined('ABSPATH'))exit;
class SMF_Attribution_Model{
 public static function models(){return array('last_touch','first_touch','first_last','assisted','position_based','time_decay');}
 public static function normalize_model($m){$m=sanitize_key((string)$m);return in_array($m,self::models(),true)?$m:'last_touch';}
 public static function label($m){$m=self::normalize_model($m);$a=array('last_touch'=>'Last Touch','first_touch'=>'First Touch','first_last'=>'First + Last (50/50)','assisted'=>'Assisted First-Touch Influence','position_based'=>'Position Based (estimate)','time_decay'=>'Time Decay (estimate)');return $a[$m];}
 public static function get_selected_campaign($first,$last,$model){$model=self::normalize_model($model);return $model==='first_touch'?$first:($last?$last:$first);}
 public static function is_different_touch($first,$last){$a=self::touch_id($first);$b=self::touch_id($last);return $a!==''&&$b!==''&&$a!==$b;}
 public static function touch_id($t){if(!is_array($t))return '';foreach(array('ad_id','adset_id','campaign_id','utm_campaign') as $k)if(!empty($t[$k]))return sanitize_text_field((string)$t[$k]);return '';}
 public static function display_name($t){if(!is_array($t))return '';foreach(array('campaign_name','utm_campaign','campaign_id') as $k)if(!empty($t[$k]))return sanitize_text_field((string)$t[$k]);return '';}
 /**
  * Allocate revenue credit. position_based/time_decay are estimates using available first/last (or touch list).
  * $weights optional: position_based => ['first'=>0.4,'middle'=>0.2,'last'=>0.4]; time_decay => ['half_life_hours'=>24]
  */
 public static function allocation($first,$last,$value,$model,$weights=array()){$m=self::normalize_model($model);$v=(float)$value;if($v<0)$v=0;$f=self::display_name($first);$l=self::display_name($last);if($f==='')$f='Direct / Unattributed';if($l==='')$l=$f;if($m==='first_touch')return array($f=>$v);if($m==='last_touch')return array($l=>$v);if($m==='assisted')return $f!==$l&&$f!=='Direct / Unattributed'?array($f=>$v):array();if($m==='position_based')return self::position_based($first,$last,$v,$weights);if($m==='time_decay')return self::time_decay($first,$last,$v,$weights);if($f!==$l)return array($f=>$v/2,$l=>$v/2);return array($f=>$v);}
 private static function position_based($first,$last,$v,$weights){$wf=isset($weights['first'])?(float)$weights['first']:0.4;$wl=isset($weights['last'])?(float)$weights['last']:0.4;$sum=$wf+$wl;if($sum<=0){$wf=0.4;$wl=0.4;$sum=0.8;}$wf/=$sum;$wl/=$sum;$f=self::display_name($first)?:'Direct / Unattributed';$l=self::display_name($last)?:$f;if($f===$l)return array($f=>$v);return array($f=>$v*$wf,$l=>$v*$wl);}
 private static function time_decay($first,$last,$v,$weights){$half=isset($weights['half_life_hours'])?max(1,(float)$weights['half_life_hours']):24;$f=self::display_name($first)?:'Direct / Unattributed';$l=self::display_name($last)?:$f;if($f===$l)return array($f=>$v);$tf=isset($first['timestamp'])?strtotime((string)$first['timestamp']):false;$tl=isset($last['timestamp'])?strtotime((string)$last['timestamp']):false;$tc=isset($weights['conversion_at'])?strtotime((string)$weights['conversion_at']):false;if(!$tf||!$tl||!$tc||$tc<$tf){return array($f=>$v*0.35,$l=>$v*0.65);}$hf=max(0,($tc-$tf)/3600);$hl=max(0,($tc-$tl)/3600);$wf=pow(0.5,$hf/$half);$wl=pow(0.5,$hl/$half);$sum=$wf+$wl;if($sum<=0)return array($l=>$v);return array($f=>$v*($wf/$sum),$l=>$v*($wl/$sum));}
}
