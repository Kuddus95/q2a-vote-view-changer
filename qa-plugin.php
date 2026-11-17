<?php

/*
	Plugin Name: q2a Upvote Changer

*/

	if ( !defined('QA_VERSION') ) { // don't allow this page to be requested directly from browser
		header('Location: ../../');
		exit;
	}

	// page

  qa_register_plugin_module('page', 'q2a-vote-view-changer.php', 'q2a_vote_view_changer_page', 'Vote View Changer');

/*
	Omit PHP closing tag to help avoid accidental output
*/
