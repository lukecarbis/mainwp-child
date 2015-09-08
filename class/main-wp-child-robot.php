<?php
/**
 * Class Main_WP_Child_Robot
 */
class Main_WP_Child_Robot {
	public static $instance = null;

	/**
	 * @return Main_WP_Child_Robot
	 */
	static function instance() {
		if ( null === Main_WP_Child_Robot::$instance ) {
			Main_WP_Child_Robot::$instance = new Main_WP_Child_Robot();
		}
		return Main_WP_Child_Robot::$instance;
	}

	/**
	 * @param $postid
	 * @param $comments
	 */
	public function wpr_insert_comments( $postid, $comments ) {
		remove_filter( 'comment_text', 'make_clickable', 9 );
		foreach ( $comments as $comment ) {
			$comment_post_ID = $postid;
			$comment_date = $comment['dts'];
			$comment_date = date( 'Y-m-d H:i:s', $comment_date );
			$comment_date_gmt = $comment_date;
			$rnd = rand( 1,9999 );
			$comment_author_email = "someone$rnd@domain.com";
			$comment_author = $comment['author'];
			$comment_author_url = '';
			$comment_content = '';
			$comment_content .= $comment['content'];
			$comment_type = '';
			$user_ID = '';
			$comment_approved = 1;
			$commentdata = compact( 'comment_post_ID', 'comment_date', 'comment_date_gmt', 'comment_author', 'comment_author_email', 'comment_author_url', 'comment_content', 'comment_type', 'user_ID', 'comment_approved' );
			wp_insert_comment( $commentdata );
		}
	}
}
