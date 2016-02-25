<?php namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
use File;
use App\Post;
use Carbon\Carbon;
use App\Option;
use App\PostMeta;
use App\Term;
use App\TermRelationship;
use DateTime;

class MainController extends Controller {

	public function __construct()
	{
		$this->siteUrl = Option::where('option_name', '=', 'siteurl')->pluck('option_value');
	}

	public function index()
	{
		// return view('home');
	}

	public function save(Request $r){
		$request = $r->input('data');
		$result = [];
		$data = File::get('demo_data.txt');
		$crawler = new Crawler($data);

		$coupons = $crawler->filter('div.offer')->each(function (Crawler $node, $i) {
		    // echo $node->html()."<br/>";
		    try{
		    	$c = [];
		    	$anchorImage = $node->filter('.anchor-image');
		    	if(!empty(trim($node->filter('.anchor-image')->html()))){
		    		$c['discount'] = $node->filter('.anchor-image > .anchor-big-text')->text();
		    	}
		    	$c['type'] = trim($node->filter('.additional-anchor')->text());
		    	$c['title'] = trim($node->filter('.title')->text());
		    	$c['description'] = trim(str_replace('Details:', '', $node->filter('.description-wrapper')->text()));
		    	$c['code'] = $node->filter('.code-text')->text();
		    	$c['rtmn_id'] = $node->attr('id');
		    	$c['out'] = 'http://www.retailmenot.com'.$node->filter('.title > a')->attr('data-main-tab');

				$c['comments'] = $node->filter('.comments > .comment_list > li')->each(function (Crawler $n, $i){
					$com = [];
		    		$com = $n->text();
		    		return trim($com);
		    	});
		    	$expire = $node->filter('.share-expire')->text();
		    	if(strpos($expire, 'Expires') !== false){
		    		$c['expiry_date'] = substr($expire, 0, 16);
		    		$c['expiry_date'] = trim(str_replace('Expires', '', $c['expiry_date']));
		    		$date = new DateTime($c['expiry_date']);
		    		$c['expiry_date'] = $date->format('Y-m-d H:i:s');
		    		$c['expiry_date'] = str_replace('00:00:00', '23:59:00', $c['expiry_date']);
		    	}
		    	return $c;
		    }catch(\Exception $e){
		    	echo $e;die;
		    }
		});
		// echo '<pre>';var_dump($coupons);die;
/*----------------------------------------------------------------------------------------*/

		foreach ($coupons as $c) {
			$objCoupon = [
				'post_title' => $c['title'],
				'post_content' => $c['description'],
				'post_name' => str_slug($c['title'], '-')
			];
			// save post
			$insertId = $this->savePost($objCoupon, $c['rtmn_id']);
			if($insertId !== null){
				$objMeta = [
					'link' => $c['out'],
					'code' => $c['code'],
					'tagline' => 'udemy coupon',
					'rtmn_id' => $c['rtmn_id']
				];
				if(isset($c['discount'])){
					$objMeta['discount'] = $c['discount'];
				}
				if(isset($c['expiry_date'])){
					$objMeta['expiry_date'] = $c['expiry_date'];
				}
				// save post meta
				$this->savePostMeta($objMeta, $insertId);
				array_push($result, $insertId);
				// link coupon to store udemy
				$this->linkToStore('Udemy', $insertId);
			}

		}
		echo '<pre>';var_dump($result);die;
		return json_encode($result);
	}

	function linkToStore($storeName, $couponId){
		$storeId = Term::where('name', '=', $storeName)->pluck('term_id');
		$tr = new TermRelationship;
		$tr->object_id = $couponId;
		$tr->term_taxonomy_id = $storeId;
		$tr->term_order = 0;
		$tr->save();
	}

	function savePost($args, $rtmnId){
		// check exist post
		$existed = PostMeta::where('meta_value', '=', $rtmnId)->pluck('meta_value');
		if($existed){return null;}

		$post = new Post;
		foreach ($args as $k=>$v) {
			$post->$k = $v;
		}
		// Default values
		$post->post_modified = Carbon::now();
		$post->post_modified_gmt = Carbon::now();
		$post->post_date = Carbon::now();
		$post->post_date_gmt = Carbon::now();
		$post->post_author = 1;
		// $post->post_status = 'draft';
		$post->post_status = 'publish';
		$post->post_type = 'coupon_type';
		$post->guid = $this->siteUrl . '/' . str_replace('-', '/', substr($post->post_date, 0, 10))
		. '/' . $args['post_name'] . '/';
		$post->save();
		return $post->id;
	}

	function savePostMeta($args, $postId){
		$args['start_date'] = Carbon::now();
		$args['ratingup'] = rand(50,100);
		$args['rating_total'] = 101;
		foreach ($args as $k=>$v) {
			$meta = new PostMeta;
			$meta->post_id = $postId;
			$meta->meta_key = $k;
			$meta->meta_value = $v;
			$meta->save();
		}
	}

	function getLastEffectiveUrl($url)
	{
	    // initialize cURL
	    $curl = curl_init($url);
	    curl_setopt_array($curl, array(
	        CURLOPT_RETURNTRANSFER  => true,
	        CURLOPT_FOLLOWLOCATION  => true,
	    ));

	    // execute the request
	    $result = curl_exec($curl);

	    // fail if the request was not successful
	    if ($result === false) {
	        curl_close($curl);
	        return null;
	    }

	    // extract the target url
	    $redirectUrl = curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
	    curl_close($curl);

	    return $redirectUrl;
	}

}
