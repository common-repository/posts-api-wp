<?php
//load functions file
require(dirname(__FILE__).'/../core/functions.php');

if (isset($_SERVER['HTTP_ORIGIN'])) {
    $http_origin = esc_url_raw($_SERVER['HTTP_ORIGIN']);
    header("Access-Control-Allow-Origin: $http_origin");
}
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Max-Age: 3600");

header("Access-Control-Allow-Headers: x-api-key, X-API-Key, Access-Control-Allow-Headers, Origin,Accept, X-Requested-With, Content-Type, Access-Control-Request-Method, Access-Control-Request-Headers");

//header("HTTP/1.1 200 OK");
function returnData($http_code, $success = false, $data)
{
    $retval = array(
        "success" => $success,
        "data" => $data
    );
    http_response_code(intval($http_code));
    return (print_r(json_encode($retval)) . exit());
}

$config = false;

//check & load configuration options
if (!empty(get_option('posts_api_wp_config'))) {
    //retrieve configuration options
    $config = json_decode(get_option('posts_api_wp_config'));
}
else {
    returnData(400, false, "API configuration does not exist");
}

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    //check if api key was provided
    if (array_key_exists('HTTP_X_API_KEY', $_SERVER)) {
        $authHeader = $_SERVER['HTTP_X_API_KEY'];
    }
    else {
        returnData(400, false, "You must provide an Authorization header");
    }

    //check if authorization key exists
    if (md5($authHeader) !== $config->authKey) {
        returnData(401, false, "Your Authorization header contains an Invalid API key");
    }

    //build query arguments
    $args = [];
    $args_not_included = ["authKey", "featuredImg", "articleUrl"];
    //11 total allowed fields and 17 total additional fields making all fields 28
    $allowed_fields = ["post_date","post_date_gmt","post_content","post_title","post_modified","post_modified_gmt","featuredImg","articleUrl","post_author_display_name","post_author_first_name","post_author_last_name"];

    //fields to dump
    $fields_to_dump = array_merge($config->additional_fields, $allowed_fields);

    foreach ($config as $key => $value) {
        //check if key should be included
        if ( !in_array($key, $args_not_included) ) {
            $args[$key] = $value;
        }
    }

    $retval = [];
    //check if posts exists
    if (!empty(get_posts($args))) {
        //loop through posts & store in return value
        foreach (get_posts($args) as $key => $obj) {
            //assign val to return value
            $retval[$key] = $obj;

            foreach ($obj as $innerKey => $innerVal) {
                //remove unnecessary fields from response object
                if(!in_array($innerKey, $fields_to_dump)){
                    unset($retval[$key]->$innerKey);
                }
                //inner key is like ID, post_author etc
                //if user wants url to the featured Image
                if ($config->featuredImg && $innerKey == 'ID') {
                    $retval[$key]->featuredImg = getPostFeaturedImage($innerVal, 'large');
                }
                //if user wants url to the post
                if ($config->articleUrl && $innerKey == 'ID') {
                    $retval[$key]->articleUrl = getPostUrl($innerVal);
                }
                //if user wants to format the post date
                if ($config->post_date_format && $innerKey == 'post_date') {
                    $retval[$key]->$innerKey = gmdate($config->post_date_format, strtotime($innerVal));
                }
                //if user wants to format the post date
                if ($config->post_date_format && $innerKey == 'post_modified') {
                    $retval[$key]->$innerKey = gmdate($config->post_date_format, strtotime($innerVal));
                }
                //if user wants to retrieve author meta
                if ($config->author_meta && $innerKey == 'post_author') {
                    //loop through the author meta fields that the user wants to see
                    foreach($config->author_meta as $metaField){
                        //check if it is available in wp
                        if( getAuthorMeta($metaField, intval($innerVal)) ){
                            $authorKey = $innerKey.'_'.$metaField;
                            //add to the return data
                            $retval[$key]->$authorKey = getAuthorMeta($metaField, intval($innerVal));
                            //remove the post author's id from array
                            if(isset($retval[$key]->post_author)) {
                                unset($retval[$key]->post_author);
                            }
                        }
                    }
                }
            }
        }
    }

    //if there are arguments
    if (count($retval) > 0) {
        returnData(200, true, $retval);
    }
    else {
        returnData(200, true, NULL);
    }

}
else {
    returnData(406, false, "Invalid Request Method");
}

print_r(json_encode($retval));
?>