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

    // How long the user can be idle before access is revoked, in seconds.
    $TOKEN_VALID_DURATION = 86400;

    // The script execution starts here.
    if (!isset( $_POST['request']) || empty($_POST['request'])) {
        error("Invalid request.");
    }

    // Switching to a particular request.
    if ($_POST['request'] === 'login') {
        login();
    } else if ($_POST['request'] === 'create_library') {
        create_library();
    } else if ($_POST['request'] === 'create_floor') {
        create_floor();
    } else if ($_POST['request'] === 'get_library_list') {
        get_library_list();
    } else if ($_POST['request'] === 'get_library') {
        get_library();
    } else if ($_POST['request'] === 'get_book_location') {
        get_book_location();
    } else if ($_POST['request'] === 'update_library') {
        update_library();
    } else if ($_POST['request'] === 'update_floor') {
        update_floor();
    } else if ($_POST['request'] === 'update_floor_meta') {
        update_floor_meta();
    } else if ($_POST['request'] === 'delete_library') {
        delete_library();
    } else if ($_POST['request'] === 'delete_floor') {
        delete_floor();
    } else {
        error("Invalid request.");
    }


    /**
     This fetches username and password information from the form, then runs
     custom credential checks. If successful, it creates a new token in the
     database and returns it to the client.

     Custom credential checks routed to third party should be executed here.

     POST parameters:
     'username':    Username entered by the client.
     'password':    Password entered by the client.

     Respose format:
     'success':     Whether login was successful.
     'error':       The error message, if not successful.
     'token':       The token for this login, if successful. This is sed as 
                    quick credential checks for subsequent db modifications. 
     */
    function login() {
        $username = $_POST['username'];
        $password = $_POST['password'];

        // Open db connection
        $con = connect();

        // Performs validation here. By default we have a username and password
        // in the database. Replace with third party authentication if required.
        $sql = 'SELECT * FROM user WHERE username = ? AND password = ?';
        $result = get_data($con, $sql, 'ss', $username, $password);

        if (count($result) != 1) {
            error('login: Invalid login credentials.');
        }

        // If access is granted, we generate a unique token.
        $token = '';
        $token_check = true;

        while ($token_check) {
            $token = bin2hex(openssl_random_pseudo_bytes(32));
            $sql = 'SELECT * FROM token WHERE token_body = ?';
            $result = get_data($con, $sql, 's', $token);
            $token_check = count($result) != 0;
        }

        // Store this token to the database along with a validity duration.
        $expire_date = time() + TOKEN_VALID_DURATION;
        $sql = 'INSERT INTO token (token_body, expire_date) VALUES (?, ?)';
        run_query($con, $sql, 'si', $token, $expire_date);

        // Closes db connection.
        $con->close();

        // Return this token along with status to the user.
        $response['success'] = $success;
        $response['token'] = $token;

        echo json_encode($response);
    }


    /**
     This creates a library in the database and returns a success flag along
     with the created library id.

     POST parameters:
     'token':           Access token given from login.
     'library_name':    Name of the new library. This must be different from
                        existing names in the database.

     Respose format:
     'success':         Whether creation was successful.
     'error':           The error message, if not successful.
     'id':              The library_id of the newly created Library record in
                        the database.
     */
    function create_library() {
        // Check user credentials.
        check_token($_POST['token']);
        
        // Parse parameters.
        $name = $_POST['name'];

        // Check if the name is already taken
        $con = connect();
        $sql = 'SELECT library_id FROM library WHERE library_name = ?';

        if (count(get_data($con, $sql, 's', $name)) > 0) {
            error("create_library: Library name is already taken.");
        }

        // Add the new library.
        $sql = 'INSERT INTO library (library_name) VALUES (?)';
        run_query($con, $sql, 's', $name);

        // Fetch the newly created library id.
        $sql = 'SELECT library_id FROM library ORDER BY library_id DESC LIMIT 1';
        $result = get_data($con, $sql, '');

        // Close database.
        $con->close();

        // Send response.
        $response['success'] = TRUE;
        $response['id'] = $result[0]->library_id;

        echo json_encode($response);
    }


    /**
     This creates a floor in the database and returns a success flag along with
     the created floor id. The newly created floor is empty.

     POST parameters:
     'token':       Access token given from login.
     'floor_name':  Name of the new floor.
     'floor_order': Order of the new floor. Essentially a number that serves to
                    order the floors. See database design document for details 
                    on how this works.
     'library_id':  Id of the library in which the floor will be created in.

     Respose format:
     'success':     Whether creation was successful.
     'error':       The error message, if not successful.
     'id':          The floor_id of the newly created Floor record in the
                    database.
     */
    function create_floor() {
        // Check user credentials.
        check_token($_POST['token']);

        // Parse parameters.
        $floor_name = $_POST['floor_name'];
        $floor_order = $_POST['floor_order'];
        $library_id = $_POST['library_id'];

        // Verify the library is valid.
        $con = connect();
        $sql = 'SELECT library_id FROM library WHERE library_id = ?';

        if (count(get_data($con, $sql, 'i', $library_id)) != 1) {
            error('create_floor: Given library is not found.');
        }

        // Add the new floor.
        $sql = 'INSERT INTO floor (floor_name, floor_order, library) 
                VALUES (?, ?, ?)';
        run_query($con, $sql, 'sdi', $floor_name, $floor_order, $library_id);

        // Fetch the newly created floor id.
        $sql = 'SELECT floor_id FROM floor ORDER BY floor_id DESC LIMIT 1';
        $result = get_data($con, $sql, '');

        // Close database.
        $con->close();

        // Send response.
        $response['success'] = TRUE;
        $response['id'] = $result[0]->floor_id;

        echo json_encode($response);
    }


    /**
     This fetches the current list of libraries from the database. Note that no 
     floor information is returned. Details of specific floor plans within a
     library can be retrieved via get_library().

     POST parameters:
     None

     Respose format:
     'success':     Whether retrieval was successful.
     'error':       The error message, if not successful.
     'data':        An array of Libraries without floors property. Refer to the
                    JSON data format for more information.
     */
    function get_library_list() {
        // Fech library list.
        $con = connect();
        $sql = 'SELECT library_id, library_name FROM library';
        $result = get_data($con, $sql, '');

        // Close database connection.
        $con->close();

        // Send response.
        $response['success'] = TRUE;
        $response['data'] = $result;

        echo json_encode($response);
    }


    /**
     This fetches all information from a library, including details on its
     floors' geometry and aisle data.

     POST parameters:
     'library_id':  Id of the library to fetch information on.

     Respose format:
     'success':     Whether retrieval was successful.
     'error':       The error message, if not successful.
     'data':        A Library object. Refer to the JSON data format for more
                    information.
     */
    function get_library() {
        // Parse parameters
        $library_id = $_POST['library_id'];

        // Verify the library is valid.
        $con = connect();
        $sql = 'SELECT * FROM library WHERE library_id = ?';
        $result = get_data($con, $sql, 'i', $library_id);

        if (count($result) != 1) {
            error('get_library: Given library is not found.');
        }

        $library = $result[0];

        // Fetch all floors for this library.
        $sql = 'SELECT floor_id FROM floor WHERE library = ?';
        $library['floors'] = get_data($con, $sql, 'i', $library_id);

        for ($i = 0; $i < count($library['floors']; $i++)) {
            $fid = $library['floors'][$i]['floor_id'];
            $library['floors'][$i] = get_floor($con, $fid);
        }

        // Close database.
        $con->close();

        // Send response.
        $response['success'] = TRUE;
        $response['data'] = $library;

        echo json_encode($response);
    }


    /**
     This takes a book's call number and its located library and tries to locate
     the exact aisle containing the book. It also provides the floor plan data
     for that floor so a map can be drawn from it.

     If multiple floors appear to contain the same book's call number, the
     lowest floor will be retrieved.

     The book's call number is stored as a string in the database, and it is in
     this function that we interpret that string and compare it with the given
     call number. This means that this is the function to modify if we want to
     use another call number system or fine tune how the call numbers should be 
     parsed.

     POST parameters:
     'library_name':    Name of the library to find the book from.
     'call_number':     The book's call number in a string representation. For
                        the format of this number in the current implementation,
                        refer to documentation on call number.

     Respose format:
     'success':         Whether retrieval was successful.
     'error':           The error message, if not successful.
     'data':            A Floor object. Refer to the JSON data format for more
                        information.
     'aisle_id':        Id of the aisle containing the given call number.
     'side':            If the aisle is double sided, this identifies which side
                        the book is on. 0 indicates the book is on the left, 1 
                        indicates the book is on the right. Left and right are 
                        relative to the local space of the stack (where we 
                        disregard its rotation). At 0 rotation, a double sided 
                        aisle should have short width and long height, with two 
                        rectangles: one on the left and the other on the right.
     */
    function get_book_location() {
        // Parse parameters
        $call_number = $_POST['call_number'];
        $library_name = $_POST['library_name'];

        // Verify the library is valid.
        $con = connect();
        $sql = 'SELECT * FROM library WHERE library_id = ?';
        $result = get_data($con, $sql, 'i', $library_id);

        if (count($result) != 1) {
            error('get_book_location: Given library is not found.');
        }

        $library = $result[0];

        // Fetch all call number ranges in this library.
        $sql = 'SELECT * 
                  FROM call_range 
                 WHERE aisle IN 
                       (SELECT aisle_id 
                          FROM aisle 
                         WHERE floor IN 
                               (SELECT floor_id 
                                  FROM floor 
                                 WHERE library = ?
                               )
                       )';
        $call_ranges = get_data($con, $sql, 'i', $library['library_id']);

        // Parse each call number range to see if the given one falls within it.


        if(count($result) != 1){
          error("The book is not found");
        }
        //echo json_encode($result[0]);
        $response['call_range'] = $result[0];
        $aid = $result[0]->aisle;
        $sql = "SELECT * FROM Aisle WHERE aid = $aid";
        $fid = getData($sql)[0]->floor;
        $sql = "SELECT * FROM Floor WHERE fid = $fid";
        //echo json_encode(getData($sql)[0]);
        $response['floor'] = getData($sql)[0];
        $response['aid'] = $aid;
        $sql = "SELECT * FROM Aisle WHERE floor = $fid";
        //echo json_encode(getData($sql));
        $response['aisle'] = getData($sql);
        $sql = "SELECT * FROM Wall WHERE floor = $fid";
        //echo json_encode(getData($sql));
        $response['wall'] = getData($sql);
        $sql = "SELECT * FROM Landmark WHERE floor = $fid";
        //echo json_encode(getData($sql));
        $response['landmark'] = getData($sql);
        echo json_encode($response);
    }


    function changeFloorName() {
        $floorId = $_POST['fid'];
        $fName = $_POST['fname'];
        $sql = "UPDATE Floor SET fname = '$fName' WHERE lid = $floorId";
        echo json_encode(getData($sql));
    }

    function deleteFloor() {
        $floorId = $_POST['fid'];
        $sql = "DELETE from Floor WHERE fid = floorId";
        echo json_encode(getData($sql));
    }

    function deleteLibrary() {
         $libId = $_POST['lid'];
         $sql = "DELETE from Library WHERE lid = libId";
         echo json_encode(getData($sql));
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

        $fid = $_POST['fid'];
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
                $callrange = $a->call_range;

                // insert aisle to database
                runQuery("INSERT INTO Aisle (center_x, center_y, length, width, rotation, sides, aislearea, floor)
                          VALUES ($cx, $cy, $length, $width, $rotation, $sides, $aaid, $fid)");

                // select the most recently added Aisle
                $aid = getData("SELECT aid FROM Aisle ORDER BY $aaid LIMIT 1");
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
          $callrange = $a->call_range;

          // insert aisle to database
          runQuery("INSERT INTO Aisle (center_x, center_y, length, width, rotation, sides, aislearea, floor)
                    VALUES ($cx, $cy, $length, $width, $rotation, $sides, NULL, $fid)");

          // select the most recently added Aisle
          $aid = getData("SELECT aid FROM Aisle ORDER BY aid DESC LIMIT 1")[0]->aid;
          foreach($callrange as $cr){
              $collection = $cr->collection;
              $callstart = $cr->callstart;
              $callend = $cr->callend;
              $side = $cr->side;

              // insert call range to database
              runQuery("INSERT INTO Call_Range (collection, callstart, callend, side, aisle)
                        VALUES ('$collection', '$callstart', '$callend', $side, $aid)");
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

         $response["success"] = TRUE;
         echo json_encode($response);
    }



    //////////////////////
    // HELPER FUNCTIONS //
    //////////////////////

    /**
     Converts a normal, non-spaced library of congress call number to a spaced
     version. If the call number is invalid, this tries to construct a call
     number from parts that are valid. However, it expects at least the class to
     be valid.

     This can also be used to check if a spaced call number is valid. If it is
     valid, this acts as the identity function. Otherwise throws an error.

     Arguments:
     $call_number:  The call number to convert, assumed to be in standard 
                    library of congress format. Refer to documentation on call 
                    number for more information.

     Return value:
     The given call number with spaces added between its parts.
     */
    function convert_call_number($call_number) {
        /*
         Format of input: ClassSubclassCutter1Cutter2, where:
         Class consists of LETTERS
         Subclass consists of DECIMAL NUMBER with decimal part optional
         Cutter1, Cutter2 consist of a DOT, followed by LETTERS, then NUMBERS.

         So by using regular expression, we can get:
         Class by       ^[A-Z]+
         Subclass by    [0-9]*\.?[0-9]+
         Cutters by     \.[a-zA-Z]+[0-9]+
         */

        // Find class
        $terminated = FALSE;
        $result = preg_match('^[A-Z]+', $call_number, $matches);

        if ($result === FALSE) {
            error('covert_call_number: Regular expression matching failed.');
        } else if ($result === 0) {
            error('covert_call_number: Call number format error. Expected class.');
        }

        $class = $matches[0];

        // Find subclass
        $result = preg_match('[0-9]*\.?[0-9]+', $call_number, $matches);

        if ($result === FALSE) {
            error('covert_call_number: Regular expression matching failed.');
        } else if ($result === 0) {
            // We should have no more parts.
            return $class;
        }

        $subclass = $matches[0];

        // Cutters
        $result = preg_match_all('\.[a-zA-Z]+[0-9]+', $call_number, $matches);

        if ($result === FALSE) {
            error('covert_call_number: Regular expression matching failed.');
        }

        $cutter1 = '';
        $cutter2 = '';

        if ($result > 0) {
            $cutter1 = $matches[0][0];
        }

        if ($result > 1) {
            $cutter2 = $matches[0][1];
        }

        if ($result > 2) {
            error('convert_call_number: Call numbers cannot have more than two cutter numbers.');
        }

        return "$class $subclass $cutter1 $cutter2";
    }


    /**
     Splits a cutter number, in the format of .LETTERSNUMBERS, into its letters
     and numbers. Throws an error if cutter is not in the correct format.

     Arguments:
     $cutter:   The cutter number to split on.

     Return value:
     An array with the first element string of the letters, second element a
     float of the numbers.
     */
    function split_cutter_number($cutter) {
        $result = preg_match('[a-zA-Z]+', $cutter, $matches);

        if ($result === FALSE) {
            error('split_cutter_number: Regular expression matching failed.');
        } else if ($result === 0) {
            error('split_cutter_number: Cutter number format error.');
        }

        $splitted[0] = $matches[0];

        $result = preg_match('[0-9]+', $cutter, $matches);

        if ($result === FALSE) {
            error('split_cutter_number: Regular expression matching failed.');
        } else if ($result === 0) {
            error('split_cutter_number: Cutter number format error.');
        }

        $splitted[1] = floatval(".$matches[0]");

        return $splitted;
    }


    /**
     Compares the two call numbers to see which one is bigger. Use 
     convert_call_number to add spaces between the parts of the call number.
     Since a call number does not need to have all parts, we compare incomplete
     call numbers as follows: if both A and B have the same class but A has 
     subclass but B does not, then A > B.

     Arguments:
     $c1:   The first call number, assumed to be in either spaced or regular
            library of congress format. Refer to documentation on call number 
            for more information.
     $c2:   The second call number, assumed to be in either spaced or regular
            library of congress format. Refer to documentation on call number 
            for more information.

     Return value:
     Returns -1 if $c1 < $c2, 0 if $c1 = $c2, and 1 if $c1 > $c2.
     */
    function compare_call_numbers($c1, $c2) {
        // First check if the two numbers are valid.
        $cmp1 = preg_split(' ', convert_call_number($c1));
        $cmp2 = preg_split(' ', convert_call_number($c2));

        // Compare class first. We are guaranteed to have at least the class by
        // the convert_call_number() function.
        if ($cmp1[0] < $cmp2[0]) {
            return -1;
        } else if ($cmp1[0] > $cmp2[0]) {
            return 1;
        }

        // Check subclass.
        if (count($cmp1) < 2 && count($cmp2) < 2) {
            // Neither has subclass.
            return 0;
        } else if (count($cmp1) >= 2 && count($cmp2) < 2) {
            // Latter does not have subclass.
            return 1;
        } else if (count($cmp1) < 2 && count($cmp2) >= 2) {
            // Former does not have subclass.
            return -1;
        }

        // Compare subclass.
        if ($cmp1[1] < $cmp2[1]) {
            return -1;
        } else if ($cmp1[1] > $cmp2[1]) {
            return 1;
        }

        // Check cutter1.
        if (count($cmp1) < 3 && count($cmp2) < 3) {
            // Neither has cutter1.
            return 0;
        } else if (count($cmp1) >= 3 && count($cmp2) < 3) {
            // Latter does not have cutter1.
            return 1;
        } else if (count($cmp1) < 3 && count($cmp2) >= 3) {
            // Former does not have cutter1.
            return -1;
        }

        // Compare cutter1.
        $cutter11 = split_cutter_number($cmp1[2]);
        $cutter12 = split_cutter_number($cmp2[2]);

        if ($cutter11[0] < $cutter12[0]) {
            return -1;
        } else if ($cutter11[0] > $cutter12[0]) {
            return 1;
        } else if ($cutter11[1] < $cutter12[1]) {
            return -1;
        } else if ($cutter11[1] > $cutter12[1]) {
            return 1;
        }

        // Check cutter2.
        if (count($cmp1) < 4 && count($cmp2) < 4) {
            // Neither has cutter2.
            return 0;
        } else if (count($cmp1) == 4 && count($cmp2) < 4) {
            // Latter does not have cutter2.
            return 1;
        } else if (count($cmp1) < 4 && count($cmp2) == 4) {
            // Former does not have cutter2.
            return -1;
        }

        // Compare cutter2.
        $cutter21 = split_cutter_number($cmp1[3]);
        $cutter22 = split_cutter_number($cmp2[3]);

        if ($cutter21[0] < $cutter22[0]) {
            return -1;
        } else if ($cutter21[0] > $cutter22[0]) {
            return 1;
        } else if ($cutter21[1] < $cutter22[1]) {
            return -1;
        } else if ($cutter21[1] > $cutter22[1]) {
            return 1;
        }

        return 0;
    }

    /**
     Retrieves all information on a floor.

     Arguments:
     $con:  MySQL connection received from connect().
     $fid:  Id of the floor to retrieve data on.

     Return value:
     Returns a Floor object before JSON conversion, so each field can be
     accessed as an associative array. Refer to the JSON data format for more 
     information.
     */
    function get_floor($con, $fid) {
        $sql = 'SELECT * FROM floor WHERE floor_id = ?';
        $result = get_data($con, $sql, 'i', $fid);

        if (count($result) != 1) {
            error('get_floor: Given floor is not found.');
        }

        $floor = $result[0];

        // Fetch all landmarks
        $sql = 'SELECT * FROM landmark WHERE floor = ?';
        $floor['landmarks'] = get_data($con, $sql, 'i', $fid);

        // Fetch all walls
        $sql = 'SELECT * FROM wall WHERE floor = ?';
        $floor['walls'] = get_data($con, $sql, 'i', $fid);

        // Fetch all aisles not belonging to an aisle area
        $sql = 'SELECT * FROM aisle WHERE floor = ? AND aisle_area = NULL';
        $floor['aisles'] = get_data($con, $sql, 'i', $fid);

        // Fetch all aisles areas
        $sql = 'SELECT * FROM aisle_area WHERE floor = ?';
        $floor['aisle_areas'] = get_data($con, $sql, 'i', $fid);

        // Fetch all aisles within those aisle areas
        foreach ($floor['aisle_areas'] as $aisle_area) {
            $aid = $aisle_area['aisle_area_id'];

            // Fetch all aisles belonging to that aisle area
            $sql = 'SELECT * FROM aisle WHERE floor = ? AND aisle_area = ?';
            $aisle_area['aisles'] = get_data($con, $sql, 'ii', $fid, $aid);
        }

        return $floor;
    }

    /**
     This checks whether the token given by the user is indeed valid in the db.
     If it is valid, the token's expiry duration is automatically extended. If
     expired, the token is deleted from the db and an error is generated.

     Arguments:
     $con:      MySQL connection received from connect().
     $token:    The token provided by the user.

     Return value:  
     Returns nothing if token is valid. Throws an exception otherwise.
     */
    function check_token($con, $token) {
        global $TOKEN_VALID_DURATION;

        // Locate token.
        $sql = "SELECT token_id, expire_date FROM token WHERE token_body = ?";
        $result = get_data($con, $sql, 's', $token);

        if (count($result) != 1) {
            error("Invalid token.");
        }

        $time = $result[0]->expire_date;
        $curr_time = time();

        // Check token validity.
        if ($time < $curr_time) {
            error("Token expired. Please login again.");
        } else {
            // If valid, refresh token duration.
            $sql = "UPDATE token SET expire_date = ? WHERE token_id = ?";
            $updated_time = $curr_time + TOKEN_VALID_DURATION;
            run_query($con, $sql, "ii", $updated_time, $result[0]->token_id);
        }
    }


    /**
     This effectively throws an exception. This will send a JSON object to the
     client with an error field. Execution of the script will be terminated
     here.
     */
    function error($msg) {
        $data['success'] = FALSE;
        $data['error'] = $msg;
        echo json_encode($data);
        die();
    }

    /**
     This connects to the database with the given credentials defined at the top
     of this script. The calling function needs to close the connection when 
     done by calling "$con->close();".

     Return value:
     A mysqli object, if successful. Throws an exception otherwise.
     */
    function connect() {
        // Create connection
        global $DB_HOST, $DB_PORT, $DB_USER, $DB_PASSWORD, $DB_NAME;
        $con = new mysqli($DB_HOST, $DB_USER, $DB_PASSWORD, $DB_NAME, $DB_PORT);

        // Check connection
        if ($mysqli->connect_errno) {
            error('Database failure: ' . $mysqli->connect_error);
        } else {
            return $con;
        }
    }

    /**
     This sends $request as a prepared SQL statement to $con, then uses $args to
     execute the query. Returns the result in a numeric array.

     Arguments:
     $con:          MySQL connection received from connect().
     $request:      a prepared MySQL statement, see 
                    php.net/manual/en/mysqli.quickstart.prepared-statements.php.
     $arg_types:    a string defining argument types to follow. 's' is string,
                    'i' is integer, 'd' is double. E.g. 'ssid' will signify that
                    there are 4 arguments to bind to the prepared statement with
                    types string, string, integer and double.
     $args:         The actual arguments, passed in to this in variable length
                    argument function style.

     Return value:  
     The result of the query, if successful, in an associative array. Throws an 
     exception otherwise.
     */
    function get_data($con, $request, $arg_types, ...$args) {
        if ($statement = $con->prepare($request)) {
            error("Error preparing statement \"$request\": $con->error");
        }

        // Bind arguments
        $types = str_split($arg_types);

        for ($i = 0; $i < count($types); $i++) {
            if (!$statement->bind_param($types[$i], $args[$i])) {
                error("Error preparing statement \"$request\": failed binding argument at index $i.");
            }
        }

        // Execute
        if (!$statement->execute()) {
            error("Error executing statement \"$request\": $statement->error");
        }

        // Fetch result
        if (!($result = $statement->get_result())) {
            error("Error getting result from statement \"$request\": $statement->error");
        }

        // We don't need to close the statement explicitly because php does that
        // for us automatically when it goes out of scope.

        // Return result
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     This does the same thing as getData() except we do not look for a result.

     Arguments:
     $con:          MySQL connection received from connect().
     $request:      a prepared MySQL statement, see 
                    php.net/manual/en/mysqli.quickstart.prepared-statements.php.
     $arg_types:    a string defining argument types to follow. 's' is string,
                    'i' is integer, 'd' is double. E.g. 'ssid' will signify that
                    there are 4 arguments to bind to the prepared statement with
                    types string, string, integer and double.
     $args:         The actual arguments, passed in to this in variable length
                    argument function style.
     */
    function run_query($con, $request, $arg_types, ...$args) {
        if ($statement = $con->prepare($request)) {
            error("Error preparing statement \"$request\": $con->error");
        }

        // Bind arguments
        $types = str_split($arg_types);

        for ($i = 0; $i < count($types); $i++) {
            if (!$statement->bind_param($types[$i], $args[$i])) {
                error("Error preparing statement \"$request\": failed binding argument at index $i.");
            }
        }

        // Execute
        if (!$statement->execute()) {
            error("Error executing statement \"$request\": $statement->error");
        }
    }
?>
