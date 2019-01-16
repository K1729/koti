<?php
// TODO: Break the conditioning!!!

// getRootDir gets the root directory of the app.
// This is useful when you need to access files
// in highly refactored environment.
function getRootDir() {
    // getcwd gives you the working directory
    $path = getcwd();
    // split the path into an array
    $arr = explode("/", $path);
    // initialize output and stop
    $output = array();
    $stop = false;
    // check each value for
    foreach ($arr as $value) {
        switch($value) {
            case "php":
            case "projects":
                $stop = true;
            default:
                array_push($output, $value);
        }
        if ($stop == true) { // stop when root is found
            break;
        }
    }
    // implode the array back into string
    return implode("/", $output);
}

// parseObject recursively reads through object vars
// and returns them in an array. Runs only 20 layers deep.
// Recursiot on perseestä. Koita muuttaa iteratiiviseksi.
function parseObject($obj, $i = 0) {
    $output = [];
    // Don't go deeper than 20
    if ($i > 20) {
        return $output;
    }
    foreach ($obj as $key=>$val) {
        if (is_object($val)) {
            $output[$key] = parseObject($val, ($i+1));
        } else {
            $output[$key] = $val;
        }
    }
    return $output;
}

// loadFile gets file and returns contents in an array
function LoadJSON($file) {
    $pwd = getRootDir() . "/" . $file;
    $output = [
        "err" => [],
        "data" => [],
    ];
    if (!file_exists($pwd)) {
        $output["err"][] = "file.loadFile: File not found";
        return $output;
    }
    $json = file_get_contents($pwd); // reads file into string
    $data = json_decode($json); // turns json into php object
    $output["data"] = parseObject($data);
    return $output;
}

// logging saves everything into a specific file
function logging($name = "koti_log.log") {
    // Reports all errors
    error_reporting(E_ALL);
    // Do not display errors for the end-users (security issue)
    ini_set('display_errors','Off');
    // Set a logging file
    ini_set('error_log',$name);


    // Override the default error handler behavior
    set_exception_handler(function($exception) {
       error_log($exception);
       error_page("Something went wrong!");
    });
}
?>