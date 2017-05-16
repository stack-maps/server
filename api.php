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
    $DB_USER = "zhenfwwh_stack-map-admin";
    $DB_PASSWORD = "stack-map-admin";
    $DB_NAME = "zhenfwwh_stack-map";

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
    } else if ($_POST['request'] === 'getBookLocation') {
        getBookLocation();
    } else if ($_POST['request'] === 'createLibrary') {
        createLibrary();
    } else if ($_POST['request'] === 'createFloor') {
        createFloor();
    } else if ($_POST['request'] === 'updateLibrary') {
        updateLibrary();
    } else if ($_POST['request'] === 'updateFloor') {
        updateFloor();
    } else {
        echo "beginning";
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

        echo "login";

        // Performs custom validation. By default we have a username and
        // password in the database.

        $success = TRUE; // As placeholder we just accept all access.

        // Generate a random string (64 hex-letters) that will serve as a token.
        $token = bin2hex(openssl_random_pseudo_bytes(32));

        // Check whether this token is already present in the database. Keep
        // generating until we get no collisions.
        $exists = FALSE; // TODO: replace with actual db check.

        $sql = "SELECT * FROM Users WHERE username = '$username' AND password = '$password'";
        $query = getData($sql);
        
        if(count($query)!=1){
            error("not found matching username and password");
        }

        
        /*
        while (mysql_num_rows($query) != 0) {
            $token = bin2hex(openssl_random_pseudo_bytes(32));
            $expiration = time() + 2000;
            $exists = FALSE; // TODO: replace with actual db check.
        }
        */

        $expiration = time();
        echo $expiration;
        echo "expiration";
        $sql = "INSERT INTO Token (token, expiration) VALUES ('$token', now())";
        runQuery($sql);

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
        $sql = "SELECT lid FROM Library";
        echo json_encode(getData($sql));
    }


    /**
     This fetches the current list of floors of a particular library from the
     database. Returns a JSON array containing information on the floors.

     This function does not need a token.
     */
    function getFloorList() {
        $libId = $_POST['lid'];

        // This will look somewhat similar to the code below.
        $sql = "SELECT * FROM Floor WHERE library = $libId";
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
        $libName = $_POST['libname'];

        // TODO.

        // first find all call number ranges in this library

        $sql = "SELECT cr.aisle FROM Call_Range cr WHERE
                (cr.callstart <= '$callNo' and cr.callend >= '$callNo') and
                cr.aisle IN
                (SELECT a.aid FROM Aisle a WHERE a.floor IN
                (SELECT f.fid FROM Floor f WHERE f.library
                IN (SELECT l.lid FROM Library l WHERE l.lname = '$libName'
                )))";
        $result = getData($sql);
        if(count($result) != 1){
          error("The book is not found");
        }
        $aid = $result[0]->aisle;
        $sql = "SELECT floor FROM Aisle WHERE aid = $aid";
        $fid = getData($sql)[0]->floor;
        echo $fid;

    }


    /**
     This creates a library in the database and returns a success flag along
     with the created library id.

     This function requires a token.
     */
    function createLibrary() {
        checkToken();

        $libName = $_POST['libname'];

        // TODO: create a new library and echo the id.
        $sql = "INSERT INTO Library (lname) VALUES ('$libName')";
        //echo $sql;
        runQuery($sql);
        $id = "SELECT lid FROM Library WHERE lname = '$libName'";
        $id_result = getData($id);
        $ans = $id_result[0];

        $success = TRUE;
        $response['success'] = $success;
        $response['id'] = $ans->lid;

        echo json_encode($response);
    }


    /**
     This creates a floor in the database and returns a success flag along with
     the created floor id. The newly created floor is empty.

     This function requires a token.
     */
    function createFloor() {
        checkToken();

        $floorName = $_POST['floorname'];
        $libName = $_POST['libname'];
        $forder = $_POST['forder'];
        echo "createfloor";
        $sql = "SELECT lid FROM Library WHERE lname = '$libName'";
        $lid = getData($sql)[0]->lid;
        echo "lid";
        echo $lid;

        // TODO: create a new floor in the library and echo the id.
        $sql = "INSERT INTO Floor (fname, forder, library) VALUES ('$floorName', '$forder', '$lid')";
        runQuery($sql);
        $id = "SELECT fid FROM Floor WHERE name = '$floorName' AND library = '$libname' ";
        echo json_encode(getData($id));
    }


    /**
     This updates a library in the database and returns a success flag.

     This function requires a token.
     */
    function updateLibrary() {
        checkToken();

        $libName = $_POST['libname'];
        $libId = $_POST['lid'];

        // TODO: updates the library with the given id with the new name.
        $sql = "UPDATE Library SET lname = '$libName' WHERE lid = $libId";
        echo json_encode(getData($sql));
    }


    /**
     This updates a floor in the database and returns a success flag.

     This function requires a token.
     */
    function updateFloor() {
        checkToken();

        $floorId = $_POST['fid'];
        $info = $_POST['floor_stuff'];

        // TODO: this is probably the longest function in the file. A floor
        // consists of walls, aisles, and aisle areas. For each of these objects
        // we first need to remove existing objects in their respective tables
        // on that floor, then add the new ones according to the data sent by
        // the user.

        // first, delete all things related to this fid -> use DELETE CASCADE
        runQuery("DELETE FROM Wall WHERE floor = $fid");
        runQuery("DELETE FROM Aisle WHERE floor = $fid");
        runQuery("DELETE FROM AisleArea WHERE floor = $fid");
        runQuery("DELETE FROM Landmark WHERE floor = $fid");

        // second, insert all things from POST
        $obj = json_decode($info);
        $aislearea = $obj->AisleArea;
        foreach($aislearea as $aa){
            $aisle = $aa->Aisle;
            $cx = $aa->center_x;
            $cy = $aa->center_y;
            $length = $aa->length;
            $width = $aa->width;
            $rotation = $aa->rotation;
            // add aisle area to database
            runQuery("INSERT INTO AisleArea (center_x, center_y, length, width, rotation, floor)
                      VALUES ($cx, $cy, $length, $width, $rotation, $fid)");

            // select the most recently added AisleArea
            $aaid = getData("SELECT aaid FROM AisleArea ORDER BY aaid LIMIT 1");
            foreach($aisle as $a){
                // add aisle  to database
                $cx = $a->center_x;
                $cy = $a->center_y;
                $length = $a->length;
                $width = $a->width;
                $rotation = $a->rotation;
                $sides = $a->sides;
                $category = $a->category;
                $callrange = $a->call_range;

                // insert aisle to database
                runQuery("INSERT INTO Aisle (center_x, center_y, length, width, rotation, sides, category, aislearea, floor)
                          VALUES ($cx, $cy, $length, $width, $rotation, $sides, '$category', $aaid, $fid)");

                // select the most recently added Aisle
                $aid = getData("SELECT aid FROM Aisle ORDER BY aaid LIMIT 1");
                foreach($callrange as $cr){
                    $collection = $cr->collection;
                    $callstart = $cr->callstart;
                    $callend = $cr->callend;
                    $side = $cr->side;
                    $aisle = $cr->aisle;

                    // insert call range to database
                    runQuery("INSERT INTO Call_Range (collection, callstart, callend, side, aisle)
                              VALUES ($collection, '$callstart', '$callend', $side, $aisle)");
                }
            }
        }

        $aisle = $obj->Aisle;
        foreach($aisle as $a){
          // add aisle  to database
          $cx = $a->center_x;
          $cy = $a->center_y;
          $length = $a->length;
          $width = $a->width;
          $rotation = $a->rotation;
          $sides = $a->sides;
          $category = $a->category;
          $callrange = $a->call_range;

          // insert aisle to database
          runQuery("INSERT INTO Aisle (center_x, center_y, length, width, rotation, sides, category, aislearea, floor)
                    VALUES ($cx, $cy, $length, $width, $rotation, $sides, '$category', $aaid, $fid)");

          // select the most recently added Aisle
          $aid = getData("SELECT aid FROM Aisle ORDER BY aaid LIMIT 1")[0]->aid;
          foreach($callrange as $cr){
              $collection = $cr->collection;
              $callstart = $cr->callstart;
              $callend = $cr->callend;
              $side = $cr->side;
              $aisle = $cr->aisle;

              // insert call range to database
              runQuery("INSERT INTO Call_Range (collection, callstart, callend, side, aisle)
                        VALUES ('$collection', '$callstart', '$callend', $side, $aisle)");
          }
      }

        $wall = $obj->Wall;
        foreach($wall as $w){
            $x1 = $w->x1;
            $y1 = $w->y1;
            $x2 = $w->x2;
            $y2 = $w->y2;

            // insert call range to database
            runQuery("INSERT INTO Wall (x1, y1, x2, y2, floor)
                      VALUES ($x1, $y1, $x2, $y2, $fid)");
        }

        $landmark = $obj->Landmark;
        foreach($landmark as $lm){
          $lname = $lm->lname;
          $center_x = $lm->center_x;
          $center_y = $lm->center_y;
          $rotation = $lm->rotation;
          $length = $lm->length;
          $width = $lm->width;

          // insert call range to database
          runQuery("INSERT INTO Landmark (lname, center_x, center_y, rotation, length, width, floor)
                    VALUES ('$lname', $center_x, $center_y, $rotation, $length, $width, $fid)");

         }
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
        $sql = "SELECT expiration FROM Token WHERE token = $token";
        //$time_result = getData($sql);
        //$time = $time_result[0]->expiration;
        //$curr_time = time();
        //$diff = $curr_time - $time;
        //echo "time";
        //echo $time;
        //echo "current time";
        //echo $curr_time;
        //echo "diff";
        //echo $diff;
        //echo $time;
        // 20 secons for each token

        /*
        if ($diff < 2000) {
            error("Invalid token. Please login again.");
        }
        */
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
        global $DB_HOST, $DB_PORT, $DB_USER, $DB_PASSWORD, $DB_NAME;
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

?>
