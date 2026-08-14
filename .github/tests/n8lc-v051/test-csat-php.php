<?php
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A','ARRAY_A');
function absint($v){return abs((int)$v);} function sanitize_key($v){return strtolower(preg_replace('/[^a-z0-9_\-]/i','',(string)$v));}
class N8LC_DB { public static function table($n){return 'wp_n8lc_'.$n;} }
class FakeWpdb {
  public $conversation=array('status'=>'closed','closed_reason'=>'visitor');
  public $counts=array('visitor_count'=>1,'agent_count'=>1);
  public function prepare($q,...$args){return $q;}
  public function get_row($q,$mode){ return strpos($q,'SUM(CASE')!==false ? $this->counts : $this->conversation; }
}
$wpdb=new FakeWpdb();
require getcwd() . '/n8-livechat-pro/includes/class-n8lc-rest.php';
$r=new ReflectionClass('N8LC_REST'); $obj=$r->newInstanceWithoutConstructor(); $m=$r->getMethod('conversation_csat_eligible'); $m->setAccessible(true);
if(!$m->invoke($obj,1,'visitor')) exit(10);
$wpdb->conversation=array('status'=>'closed','closed_reason'=>'idle'); if($m->invoke($obj,1,'idle')) exit(11);
$wpdb->conversation=array('status'=>'closed','closed_reason'=>'agent'); if($m->invoke($obj,1,'agent')) exit(14);
$wpdb->conversation=array('status'=>'closed','closed_reason'=>'visitor'); $wpdb->counts=array('visitor_count'=>1,'agent_count'=>0); if($m->invoke($obj,1,'visitor')) exit(12);
$wpdb->counts=array('visitor_count'=>1,'agent_count'=>1); $wpdb->conversation=array('status'=>'open','closed_reason'=>''); if($m->invoke($obj,1,'')) exit(13);
echo "CSAT backend eligibility test passed\n";
