<?php

/*
    Plugin Name: Upvote Changer
*/

class q2a_vote_view_changer_page
{
    var $directory;
    var $urltoroot;

    function load_module($directory, $urltoroot)
    {
        $this->directory = $directory;
        $this->urltoroot = $urltoroot;
    }

    function suggest_requests()
    {
        return array(
            array(
                'title' => 'Vote View Changer',
                'request' => 'voteview',
                'nav' => 'M',
            ),
        );
    }

    // for url query
    function match_request($request)
    {
        if ($request == 'voteview') {
            return true;
        }

        return false;
    }

    function process_request($request)
    {
        $level = qa_get_logged_in_level();
        if ($level < QA_USER_LEVEL_ADMIN) {
            $qa_content = qa_content_prepare();
            $qa_content['error'] = 'You must be an admin to use this feature.';
            return $qa_content;
        }

        $qa_content = qa_content_prepare();
        $qa_content['title'] = 'Vote View Changer';
        $qa_content['custom'] = '';

        $qa_content['custom'] .= '
        <style>
            #convdiv, .qa-main p, .qa-main a, .qa-main input { 
                font-size:14px; padding:2px 5px;
            }
            .qa-main h1 {
                margin-bottom:40px;
            }
            .qa-main input {
                border:1px solid #CCC;
                padding:5px;
            }
            
            .success-message {
                color: green;
                margin: 20px auto; 
                text-align: center; 
                width: 80%; 
                max-width: 600px; 
            }
            
            .error-message {
                color: red;
                margin: 20px auto; 
                text-align: center; 
                width: 80%; 
                max-width: 600px; 
            }

        </style>
        ';

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $postid = qa_post_text('postid');
            $increment_value = qa_post_text('increment_value');
            $increment_views = qa_post_text('increment_views'); 

            // Initialize variables to avoid undefined variable errors
            $updateVoteQuery = null;
            $updateUpvotesQuery = null;
            $updateViewsQuery = null;

            // Check if Post ID is empty
            if (empty($postid)) {
                $qa_content['custom'] .= '<p class="error-message">Post ID is required.</p>';
                return $qa_content;
            }

            // Condition: At least one field (upvote or views) should be provided
            if (empty($increment_value) && empty($increment_views)) {
                $qa_content['custom'] .= '<p class="error-message">At least one value is reqired.</p>';
                return $qa_content;
            }

            // Validate Upvote Value (if provided)
            if (!empty($increment_value) && (!is_numeric($increment_value) || $increment_value <= 0)) {
                $qa_content['custom'] .= '<p class="error-message">Invalid upvote value.</p>';
                return $qa_content;
            }

            // Validate Views Value (if provided)
            if (!empty($increment_views) && (!is_numeric($increment_views) || $increment_views < 0)) {
                $qa_content['custom'] .= '<p class="error-message">Invalid views value.</p>';
                return $qa_content;
            }

            qa_db_query_sub('START TRANSACTION');

            try {
                // Get post type (question, answer, or comment)
                $post_type = qa_db_read_one_value(qa_db_query_sub(
                    'SELECT `type` FROM `^posts` WHERE `postid` = # LIMIT 1',
                    $postid
                ));

                // If post type is invalid
                if (!$post_type) {
                    throw new Exception('Invalid post type');
                }

                // Get the current vote of the user (whether it's a question, answer, or comment)
                $current_vote = qa_db_read_one_value(qa_db_query_sub(
                    'SELECT `vote` FROM `^uservotes` WHERE `postid` = # AND `userid` = 1 LIMIT 1',
                    $postid
                ), true); // Pass 'true' to get null if no result.

                // If no entry exists, set the current_vote to 0
                if ($current_vote === null) {
                    $current_vote = 0;
                }

                // Increment the vote value (if provided)
                if (!empty($increment_value)) {
                    $new_vote_value = $current_vote + (int)$increment_value;

                    // Update the vote in `qa_uservotes`
                    $updateVoteQuery = qa_db_query_sub('
                        INSERT INTO `^uservotes` (`postid`, `userid`, `vote`)
                        VALUES (#, 1, #)
                        ON DUPLICATE KEY UPDATE `vote` = #',
                        $postid, $new_vote_value, $new_vote_value);

                    // Update the upvotes based on the post type
                    if ($post_type == 'Q') {
                        // If it's a question
                        $updateUpvotesQuery = qa_db_query_sub('
                            UPDATE `^posts`
                            SET `upvotes` = `upvotes` + # 
                            WHERE `postid` = # LIMIT 1', 
                            $increment_value, $postid);
                    } elseif ($post_type == 'A') {
                        // If it's an answer
                        $updateUpvotesQuery = qa_db_query_sub('
                            UPDATE `^posts`
                            SET `upvotes` = `upvotes` + # 
                            WHERE `postid` = # LIMIT 1', 
                            $increment_value, $postid);
                    } elseif ($post_type == 'C') {
                        // If it's a comment
                        $updateUpvotesQuery = qa_db_query_sub('
                            UPDATE `^comments`
                            SET `upvotes` = `upvotes` + # 
                            WHERE `commentid` = # LIMIT 1',
                            $increment_value, $postid);
                    } else {
                        throw new Exception('Unknown post type');
                    }
                }

                // Increment the views if a valid views increment is provided
                if (!empty($increment_views)) {
                    $updateViewsQuery = qa_db_query_sub('
                        UPDATE `^posts`
                        SET `views` = `views` + # 
                        WHERE `postid` = # LIMIT 1',
                        $increment_views, $postid);
                }

                // Commit changes if both queries are successful
                if (($updateVoteQuery || empty($increment_value)) && ($updateUpvotesQuery || empty($increment_value)) && ($updateViewsQuery || empty($increment_views))) {
                    qa_db_query_sub('COMMIT');
                    $qa_content['custom'] .= '<p class="success-message">Information updated successfully !</p>';
                } else {
                    qa_db_query_sub('ROLLBACK');
                    $qa_content['custom'] .= '<p class="error-message">Upvote and Views update fail.</p>';
                }
            } catch (Exception $e) {
                qa_db_query_sub('ROLLBACK');
                $qa_content['custom'] .= '<p class="error-message">Upvote and Views update fail.</p>';
            }
        }

        // Display the form if no data is processed yet
        $qa_content['custom'] .= '
        <center>
            <div id="convdiv">
                <form name="uploadform" method="post" action="'.qa_self_html().'">
                    <label for="postid">Post ID : </label>
                    <input name="postid" id="postid" type="text" placeholder="Insert Post ID" autofocus><br />
                    <br />
                    
                    <label for="increment_value">Upvote Value : </label>
                    <input name="increment_value" id="increment_value" type="text" placeholder="Insert Vote value" ><br />
                    
                    Or
                    <br />
                    <label for="increment_views">Views Value : </label>
                    <input name="increment_views" id="increment_views" type="text" placeholder="Insert Views value" ><br />
                    <br />

                    <input type="submit" value="Update Record" class="qa-form-basic-button"><br /><br /><br /><br />
                </form>
            </div>
        </center>';

        return $qa_content;
    } 
}

/*
    Omit PHP closing tag to help avoid accidental output
*/

?>
