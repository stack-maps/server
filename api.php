/**
 * Stack Maps
 *
 * Author: Jiacong Xu
 * Created on: Sep/23/2014
 */

<?php
    /*
     These information are used to connect to the database that manages the
     stack maps.
     */
    $DB_HOST = "localhost";
    $DB_PORT = "3306";
    $DB_USER = "admin";
    $DB_PASSWORD = "admin";
    
    // The main logic of the execution starts here.
    if (!isset( $_GET['request']) || empty($_GET['request'])) {
        // The request parameter must be set.
        error("Invalid request.");
    }
    
    // Switching to a particular request.
    if ($_GET['request'] === 'Request A') {
        // Call a helper function.
    } else if ($_GET['request'] === 'Request B') {
        // Call a helper function.
    } else {
        error("Invalid request.");
    }
    
    
    
    
    /**
     Below are several example fetch and set usages from a personal project.
     These should serve as guides to how to write functions to do what we want
     to do in Stack Maps.
     */
    
    
    
    
    
    
    /**
     * This echoes hiscores from the given list of Facebook Ids.
     */
    function getFriendHiscore() {
        $ids = $_POST['ids'];
        $isSD = $_POST['isSD'];
        $order = "score";
        
        if ($isSD > 0) {
            $order = "sd";
        }
        
        $sql = "SELECT * FROM hiscore
        WHERE score > 0 AND facebookId IN ($ids)
        ORDER BY $order DESC, star, date";
        
        echo json_encode(getData($sql));
    }
    
    /**
     * This echoes the length number of hiscores from the given index.
     */
    function getHiscore() {
        $index = $_GET['index'];
        $length = $_GET['length'];
        
        $sql = "SELECT * FROM hiscore
        WHERE score > 0
        ORDER BY score DESC, star, date
        LIMIT $index, $length";
        
        echo json_encode(getData($sql));
    }
    
    /**
     * This echoes the length number of hiscores from the given index.
     */
    function getSDHiscore() {
        $index = $_GET['index'];
        $length = $_GET['length'];
        
        $sql = "SELECT * FROM hiscore
        WHERE sd > 0
        ORDER BY sd DESC, star, date
        LIMIT $index, $length";
        
        echo json_encode(getData($sql));
    }
    
    /**
     * This echoes the rank and percentile of the given player.
     */
    function findHiscore() {
        $id = $_GET['id'];
        
        $sql1 = "SELECT COUNT(*) + 1 as rank FROM hiscore, (SELECT score, date, star FROM hiscore WHERE id = $id) AS I
        WHERE hiscore.score > I.score OR (hiscore.score = I.score AND hiscore.star < I.star)
        OR (hiscore.score = I.score AND hiscore.star = I.star AND hiscore.date < I.date)";
        
        $sql1_result = getData($sql1);
        
        $sql2 = "SELECT COUNT(*) as total FROM hiscore WHERE hiscore.score > 0";
        
        $sql2_result = getData($sql2);
        
        if (count($sql1_result) != 1) {
            error("Invalid id.");
        }
        
        $result = $sql1_result[0];
        $result->total = $sql2_result[0]->total;
        
        echo json_encode($result);
    }
    
    /**
     * This echoes the rank and percentile of the given player.
     */
    function findSDHiscore() {
        $id = $_GET['id'];
        
        
        $sql1 = "SELECT COUNT(*) + 1 as rank FROM hiscore, (SELECT sd, date FROM hiscore WHERE id = $id) AS I
        WHERE hiscore.sd > I.sd OR (hiscore.sd = I.sd AND hiscore.date < I.date)";
        
        $sql1_result = getData($sql1);
        
        $sql2 = "SELECT COUNT(*) as total FROM hiscore WHERE hiscore.sd > 0";
        
        $sql2_result = getData($sql2);
        
        if (count($sql1_result) != 1) {
            error("Invalid id.");
        }
        
        $result = $sql1_result[0];
        $result->total = $sql2_result[0]->total;
        
        echo json_encode($result);
    }
    
    
    /**
     * This echoes the star rank.
     */
    function findHistar() {
        $id = $_GET['id'];
        
        
        $sql = "SELECT COUNT(*) + 1 as rank FROM hiscore, (SELECT star, date FROM hiscore WHERE id = $id) AS I
        WHERE hiscore.star > I.star OR (hiscore.star = I.star AND hiscore.date < I.date)";
        
        $sql_result = getData($sql);
        
        if (count($sql_result) != 1) {
            error("Invalid id.");
        }
        
        echo json_encode($sql_result[0]);
    }
    
    
    function postHiscore() {
        $id = $_POST['id'];
        $name = urldecode($_POST['name']);
        $hiscore = $_POST['hiscore'];
        $sd = $_POST['sd'];
        $star = $_POST['star'];
        $fbid = '';
        $uuid = '';
        
        if (array_key_exists('fbid', $_POST)) {
            $fbid = $_POST['fbid'];
        }
        
        if (array_key_exists('uuid', $_POST)) {
            $uuid = $_POST['uuid'];
        }
        
        // Update username and
        $sql = "UPDATE hiscore
        SET name = '$name',
        score = $hiscore,
        sd = $sd,
        star = $star,
        facebookId = '$fbid',
        uuid = '$uuid',
        date = CURRENT_TIMESTAMP
        WHERE id = $id";
        runQuery($sql);
        
        $response['completion'] = "Updated.";
        echo json_encode($response);
    }
    
    /**
     * This generates a new UID and send it back to the user.
     */
    function getUID() {
        $hash = $_GET['hash'];
        
        if (array_key_exists('uuid', $_GET)) {
            $uuid = $_GET['uuid'];
            
            // Try to find an entry.
            $sql = "SELECT id FROM hiscore WHERE uuid = '$uuid'";
            $res = getData($sql);
            
            if (count($res) == 1) {
                echo json_encode($res);
                
                return;
            } else {
                $sql = "INSERT INTO hiscore
                (id, name, score, star, uuid) VALUES
                (NULL, '', 0, 0, '$uuid')";
                runQuery($sql);
            }
        } else {
            $sql = "INSERT INTO hiscore
            (id, name, score, star) VALUES
            (NULL, '', 0, 0)";
            runQuery($sql);
        }
        
        $sql = 'SELECT id
        FROM hiscore
        ORDER BY id DESC
        LIMIT 1';
        echo json_encode(getData($sql));
    }
    
    //////////////////////
    // HELPER FUNCTIONS //
    //////////////////////
    
    /**
     * This echoes an error to the client with the given message.
     */
    function error($msg) {
        $data['error'] = $msg;
        echo json_encode($data);
        die();
    }
    
    /**
     * This connects to the database. The calling function needs to close the
     * connection when done, though.
     */
    function connect() {
        // Create connection
        $con = new mysqli($DB_HOST, $DB_USER, $DB_PASSWORD, $DB_NAME, $DB_PORT);
        
        // Check connection
        if (mysqli_connect_errno()) {
            $data['error'] = 'Database failure: ' . mysqli_connect_error();
            echo json_encode($data);
        } else {
            return $con;
        }
    }
    
    /**
     * This returns a JSON representation of MySQL fetch request.
     */
    function getData($request) {
        $con = connect();
        $result = mysqli_query($con, $request);
        mysqli_close($con);
        
        // Check if there are results
        if ($result) {
            // If so, then create a results array and a temporary one
            // to hold the data
            $resultArray = array();
            $tempArray = array();
            
            // Loop through each row in the result set
            while ($row = $result->fetch_object()) {
                // Add each row into our results array
                $tempArray = $row;
                array_push($resultArray, $tempArray);
            }
            
            // Finally, output the results
            return $resultArray;
        } else {
            // Return empty array
            return array();
        }
    }
    
    /**
     * This runs the given query without returning anything.
     */
    function runQuery($request) {
        $con = connect();
        $result = mysqli_real_query($con, $request);
        
        mysqli_close($con);
    }
?>
