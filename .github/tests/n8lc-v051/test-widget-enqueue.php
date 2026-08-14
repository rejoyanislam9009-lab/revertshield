<?php
define('ABSPATH', __DIR__ . '/'); define('ARRAY_A','ARRAY_A'); define('N8LC_URL','https://example.test/wp-content/plugins/n8-livechat-pro/'); define('N8LC_VERSION','0.5.1');
$styles=array(); $scripts=array();
function get_option($k,$d=array()){return array('enabled'=>1,'widget_title'=>'Chat','position'=>'right','accent_color'=>'#111827','require_email'=>0,'poll_interval'=>3000,'uploads_enabled'=>1,'max_upload_mb'=>5,'csat_enabled'=>1,'offline_message'=>'Away');}
function wp_enqueue_style($h,$src,$deps=array(),$v=null){global $styles;$styles[]=array($h,$src,$deps,$v);} function wp_enqueue_script($h,$src,$deps=array(),$v=null,$footer=false){global $scripts;$scripts[]=array($h,$src,$deps,$v,$footer);} function wp_localize_script(){}
function rest_url($v){return 'https://example.test/wp-json/'.$v;} function esc_url_raw($v){return $v;} function absint($v){return abs((int)$v);} function __($v){return $v;}
class N8LC_Visual { static function get(){return array('theme_preset'=>'emerald','launcher_icon'=>'message','launcher_shape'=>'circle','launcher_size'=>64,'launcher_label'=>'','launcher_animation'=>'none','show_greeting'=>0,'greeting_text'=>'','greeting_delay'=>1,'greeting_auto_hide'=>12,'agent_name'=>'Support','agent_avatar_url'=>'','header_subtitle'=>'Online','panel_width'=>400,'panel_height'=>660,'panel_radius'=>24,'sound_enabled'=>0,'show_branding'=>0);} }
class N8LC_Presence { static function status(){return 'online';} } class N8LC_DB { static function table($n){return 'wp_n8lc_'.$n;} } class N8LC_REST { const NS='n8lc/v1'; } class N8LC_Availability { static function is_open($s=null){return true;} }
class FakeDb { function get_results(){return array();} } $wpdb=new FakeDb();
require getcwd() . '/n8-livechat-pro/includes/class-n8lc-widget.php';
N8LC_Widget::instance()->enqueue();
$handles=array_column($styles,0);
if($handles!==array('n8lc-widget','n8lc-widget-experience')){fwrite(STDERR,json_encode($styles));exit(10);} if($styles[1][2]!==array('n8lc-widget')) exit(11);
echo "widget stylesheet enqueue runtime test passed\n";
