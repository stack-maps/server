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
    if (!isset( $_POST['request']) || empty($_POST['request'])) {
        // The request parameter must be set.
        error("Invalid request.");
    }
    
    // Switching to a particular request.
    if ($_POST['request'] === 'login') {
        login();
    } else if ($_POST['request'] === 'getLibraryList') {
        getLibraryList();
    } else if ($_POST['request'] === 'getFloorList') {
        getFloorList();
    } else if ($_POST['request'] === 'createLibrary') {
        createLibrary();
    } else if ($_POST['request'] === 'createFloor') {
        createFloor();
    } else if ($_POST['request'] === 'updateLibrary') {
        updateLibrary();
    } else if ($_POST['request'] === 'updateFloor') {
        updateFloor();
    } else {
        error("Invalid request.");
    }
    
    
    /**
     This fetches username and password information from the form, then runs
     custom credential checks. If successful, it creates a new token in the
     database and returns it to the client.
     
     Custom credential checks routed to third party should be executed here.
     
     Returns a JSON encoded object to user with a flag for login success
     ('success') and a token ('token') if login was successful.
     */
    function login() {
        $username = $_POST['username'];
        $password = $_POST['password'];
        
        // Performs custom validation. By default we have a username and
        // password in the database.
        
        $success = TRUE; // As placeholder we just accept all access.
        
        // Generate a random string (64 hex-letters) that will serve as a token.
        $token = bin2hex(openssl_random_pseudo_bytes(32));
        
        // Check whether this token is already present in the database. Keep
        // generating until we get no collisions.
        $exists = FALSE; // TODO: replace with actual db check.
        
        while ($exists) {
            $token = bin2hex(openssl_random_pseudo_bytes(32));
            $exists = FALSE; // TODO: replace with actual db check.
        }
        
        // Store this token to the database along with a validity duration.
        
        // Return this token along with status to the user.
        $response['success'] = $success;
        $response['token'] = $token;
        
        echo json_encode($response);
    }
    
    
    /**
     This fetches the current list of libraries from the database. Returns a
     JSON array containing information on the libraries. Note that no floor
     information is returned. 
     
     This function does not need a token.
     */
    function getLibraryList() {
        // This will look somewhat similar to the code below.
        $sql = "SELECT lid, name FROM library";
        echo json_encode(getData($sql));
    }
    
    
    /**
     This fetches the current list of floors of a particular library from the 
     database. Returns a JSON array containing information on the floors.
     
     This function does not need a token.
     */
    function getFloorList() {
        $libId = $_POST['lid'];
        
        if (!is_int($libId)) {
            error("Invalid request.");
        }
        
        // This will look somewhat similar to the code below.
        $sql = "SELECT * FROM floor WHERE lid = $libId";
        echo json_encode(getData($sql));
    }
    
    
    /**
     This function takes a book's call number and its located library and try to
     locate the exact aisle containing the book. We return a JSON object
     containing the floor (which a map can be drawn from) and the aisle id that
     contains the book.
     
     This function does not need a token.
     */
    function getBookLocation() {
        $callNo = $_POST['callno'];
        $libName = $_POST['library'];
        
        // TODO.
    }
    
    
    /**
     This creates a library in the database and returns a success flag along
     with the created library id.
     
     This function requires a token.
     */
    function createLibrary() {
        checkToken();
        
        $libName = $_POST['name'];
        
        // TODO: create a new library and echo the id.
    }
    
    
    /**
     This creates a floor in the database and returns a success flag along with
     the created floor id. The newly created floor is empty.
     
     This function requires a token.
     */
    function createFloor() {
        checkToken();
        
        $floorName = $_POST['name'];
        
        // TODO: create a new floor in the library and echo the id.
    }
    
    
    /**
     This updates a library in the database and returns a success flag.
     
     This function requires a token.
     */
    function updateLibrary() {
        checkToken();
        
        $libName = $_POST['name'];
        $libId = $_POST['lid'];
        
        // TODO: updates the library with the given id with the new name.
    }
    
    
    /**
     This updates a floor in the database and returns a success flag.
     
     This function requires a token.
     */
    function updateFloor() {
        checkToken();
        
        $floorId = $_POST['fid'];
        
        // TODO: this is probably the longest function in the file. A floor
        // consists of walls, aisles, and aisle areas. For each of these objects
        // we first need to remove existing objects in their respective tables
        // on that floor, then add the new ones according to the data sent by
        // the user.
    }
    
    
    
    //////////////////////
    // HELPER FUNCTIONS //
    //////////////////////
    
    /**
     This checks whether the token given by the user is indeed valid in the db.
     If it is valid, the token's expiry duration is automatically extended. If
     expired, the token is deleted from the db and an error is generated.
     */
    function checkToken() {
        $token = $_POST['token'];
        
        // TODO: Checks whether it is valid.
        $valid = TRUE;
        
        if (!$valid) {
            error("Invalid token. Please login again.");
        }
    }
    
    
    /**
     This echoes an error to the client with the given message.
     */
    function error($msg) {
        $data['error'] = $msg;
        echo json_encode($data);
        die();
    }
    
    /**
     This connects to the database. The calling function needs to close the
     connection when done, though.
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
     This returns a JSON representation of MySQL fetch request.
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
     This runs the given query without returning anything.
     */
    function runQuery($request) {
        $con = connect();
        $result = mysqli_real_query($con, $request);
        
        mysqli_close($con);
    }
    
    
    
    
    
    
    
    
    
    
    
    
    
    /**
     Below are several example fetch and set usages from a personal project.
     These should serve as guides to how to write functions to do what we want
     to do in Stack Maps.
     */
    
    /**
     This echoes hiscores from the given list of Facebook Ids.
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
     This echoes the length number of hiscores from the given index.
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
     This echoes the length number of hiscores from the given index.
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
     This echoes the rank and percentile of the given player.
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
     This echoes the rank and percentile of the given player.
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
     This echoes the star rank.
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
     This generates a new UID and send it back to the user.
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
?>
