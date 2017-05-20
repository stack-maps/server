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
    $endpoints = array('login', 'create_library', 'create_floor',
      'get_library_list', 'get_library', 'get_book_location', 'update_library',
      'update_floor', 'update_floor_meta', 'delete_library', 'delete_floor');

    $endpoint = $_POST['request'];

    if (function_exists($endpoint) && in_array($endpoint, $endpoints, true)) {
      $endpoint();
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

     Response format:
     'success':     Whether login was successful.
     'error':       The error message, if not successful.
     'token':       The token for this login, if successful. This is used as 
                    quick credential checks for subsequent db modifications. 
     */
    function login() {
        // Parse parameters.
        $username = $_POST['username'];
        $password = $_POST['password'];

        // Open db connection.
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

        // Close db connection.
        $con->close();

        // Send response.
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

     Response format:
     'success':         Whether creation was successful.
     'error':           The error message, if not successful.
     'id':              The library_id of the newly created Library record in
                        the database.
     */
    function create_library() {
        $con = connect();

        // Check user credentials.
        check_token($con, $_POST['token']);
        
        // Parse parameters.
        $name = $_POST['name'];

        // Check if the name is already taken.
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

     Response format:
     'success':     Whether creation was successful.
     'error':       The error message, if not successful.
     'id':          The floor_id of the newly created Floor record in the
                    database.
     */
    function create_floor() {
        $con = connect();

        // Check user credentials.
        check_token($con, $_POST['token']);

        // Parse parameters.
        $floor_name = $_POST['floor_name'];
        $floor_order = $_POST['floor_order'];
        $library_id = $_POST['library_id'];

        // Verify the library is valid.
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

     Response format:
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

     Response format:
     'success':     Whether retrieval was successful.
     'error':       The error message, if not successful.
     'data':        A Library object. Refer to the JSON data format for more
                    information.
     */
    function get_library() {
        // Parse parameter.
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

     If multiple floors appear to contain the same book's call number, multiple
     floors will be returned. It is up to the caller to decide on what to do
     with the data.

     The book's call number is stored as a string in the database, and it is in
     this function that we interpret that string and compare it with the given
     call number. This means that this is the function to modify if we want to
     use another call number system or fine tune how the call numbers should be 
     parsed.

     For different sizes, we use a simple suffix system to determine whether we
     are in the same size. A call number will have zero or more '+' at the end,
     which we can use to compare to the 'collection' property of the call range.

     POST parameters:
     'library_name':    Name of the library to find the book from.
     'call_number':     The book's call number in a string representation. For
                        the format of this number in the current implementation,
                        refer to documentation on call number.

     Response format:
     'success':         Whether retrieval was successful.
     'error':           The error message, if not successful.
     'floors':          A numeric array of Floor object. Refer to the JSON data 
                        format for more information.
     'aisles':          A numeric array of associative arrays each containing
                        two elements, an 'aisle_id' and a 'side'. The former is
                        the id of the aisle containing the given call number.
                        The latter is present only if the aisle is double sided,
                        in which case 'side' identifies which side the book is 
                        on. 0 indicates the book is on the left, 1 indicates the 
                        book is on the right. Left and right are relative to the 
                        local space of the stack (where we disregard its 
                        rotation). At 0 rotation, a double sided aisle should 
                        have short width and long height, with two rectangles: 
                        one on the left and the other on the right.
     */
    function get_book_location() {
        // Parse parameters.
        $call_number = $_POST['call_number'];
        $library_name = $_POST['library_name'];
        $collection = '';

        if (preg_match('(\++$)', $call_number, $matches)) {
            $collection = $matches[0];
        }

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
                  JOIN aisle ON call_range.aisle = aisle.aisle_id
                 WHERE collection = ?
                   AND aisle IN 
                       (SELECT aisle_id 
                          FROM aisle 
                         WHERE floor IN 
                               (SELECT floor_id 
                                  FROM floor 
                                 WHERE library = ?
                               )
                       )';
        $call_ranges = get_data($con, $sql, 'si', $collection, $library['library_id']);

        if (count($call_ranges) === 0) {
          error("get_book_location: The book is not found in this library.");
        }

        $aisles = array();
        $floor_ids = array();

        // Parse each call number range to see if the given one falls within it.
        foreach ($call_ranges as $call_range) {
            $range_start = $call_range['call_start'];
            $range_end = $call_range['call_end'];

            if (compare_call_numbers($range_start, $call_number) <= 0 &&
                compare_call_numbers($call_number, $range_end) < 0) {
                // Call number is within this range.
                $aisle_element['aisle_id'] = $call_range['aisle'];

                if ($call_range['side'] !== NULL) {
                    $aisle_element['side'] = $call_range['side'];
                }

                // Add fid to the list of floors to fetch
                $fid = $call_range['floor'];

                if (!in_array($fid, $floor_ids)) {
                    array_push($floor_ids, $fid);
                }

                // Add aisle element to array.
                array_push($aisles, $aisle_element);
            }
        }

        // Now fetch each floor.
        $floors = array();

        foreach ($floor_ids as $fid) {
            array_push($floors, get_floor($con, $fid));
        }

        // Close database.
        $con->close();

        // Send response.
        $response['success'] = TRUE;
        $response['floors'] = $floors;
        $response['aisles'] = $aisles;

        echo json_encode($response);
    }


    /**
     This updates a library's name. Note each library must have a unique name.

     POST parameters:
     'token':           Access token given from login.
     'library_id':      Id of the library to perform the change to.
     'library_name':    New name of the library.

     Response format:
     'success':         Whether retrieval was successful.
     'error':           The error message, if not successful.
     */
    function update_library() {
        $con = connect();

        // Check user credentials.
        check_token($con, $_POST['token']);

        // Parse parameters.
        $new_name = $_POST['library_name'];
        $library_id = $_POST['library_id'];

        // Verify the change is valid.
        $sql = 'SELECT * FROM library WHERE library_id = ? OR library_name = ?';
        $result = get_data($con, $sql, 'is', $library_id, $new_name);

        if (count($result) != 1) {
            error('update_library: Given library is not found or name is taken.');
        }

        // Update library name.
        $sql = 'UPDATE library SET library_name = ? WHERE library_id = ?';
        $run_query($con, $sql, 'si', $new_name, $library_id);

        // Close database.
        $con->close();

        // Send response.
        $response['success'] = TRUE;

        echo json_encode($response);
    }


    /**
     This updates a floor of a library. The floor includes aisles, call number
     ranges in aisles, aisle areas, aisles in aisle areas, call number ranges in
     aisles in aisle areas, landmarks, and walls.

     POST parameters:
     'token':   Access token given from login.
     'floor':   Floor object encoded in JSON. Refer to the JSON data format 
                for more information.

     Response format:
     'success': Whether update was successful.
     'error':   The error message, if not successful.
     */
    function update_floor() {
        $con = connect();

        // Check user credentials.
        check_token($con, $_POST['token']);

        // Parse parameters.
        $floor = json_decode($_POST['floor'], TRUE);

        // Perform validation.
        validate_floor($floor);

        $floor_id = $floor['floor_id'];

        // Verify the floor exists.
        $sql = 'SELECT floor_id FROM floor WHERE floor_id = ?';

        if (count(get_data($con, $sql, 'i', $floor_id)) != 1) {
            error('update_floor: Given floor is not found.');
        }

        // Delete existing objects on the floor.
        $sql = 'DELETE FROM wall WHERE floor = ?';
        run_query($con, $sql, 'i', $floor_id);

        $sql = 'DELETE FROM aisle WHERE floor = ?';
        run_query($con, $sql, 'i', $floor_id);

        $sql = 'DELETE FROM aisle_area WHERE floor = ?';
        run_query($con, $sql, 'i', $floor_id);

        $sql = 'DELETE FROM landmark WHERE floor = ?';
        run_query($con, $sql, 'i', $floor_id);

        // Add walls into the database.
        if (array_key_exists('walls', $floor)) {
            $sql = 'INSERT INTO wall (start_x, start_y, end_x, end_y, floor)
                    VALUES (?, ?, ?, ?, ?)';

            foreach ($floor['walls'] as $wall) {
                run_query($con, $sql, 'ddddi', $wall['start_x'], 
                    $wall['start_y'], $wall['end_x'], $wall['end_y'], $floor_id);
            }
        }

        // Add landmarks into the database.
        if (array_key_exists('landmarks', $floor)) {
            $sql = 'INSERT INTO landmark (landmark_type, center_x, center_y, width, height, rotation, floor)
                    VALUES (?, ?, ?, ?, ?, ?, ?)';

            foreach ($floor['landmarks'] as $landmark) {
                run_query($con, $sql, 'sdddddi', $landmark['landmark_type'], 
                    $landmark['center_x'], $landmark['center_y'], 
                    $landmark['width'], $landmark['height'], 
                    $landmark['rotation'], $floor_id);
            }
        }

        // Add aisles into the database.
        if (array_key_exists('aisles', $floor)) {
            $sql = 'INSERT INTO aisle (center_x, center_y, width, height, rotation, is_double_sided, floor)
                    VALUES (?, ?, ?, ?, ?, ?, ?)';

            foreach ($floor['aisles'] as $aisle) {
                run_query($con, $sql, 'dddddii', $aisle['center_x'], 
                    $aisle['center_y'], $aisle['width'], $aisle['height'], 
                    $aisle['rotation'], intval($aisle['is_double_sided']), 
                    $floor_id);

                // Insert call ranges.
                if (array_key_exists('call_ranges', $aisle)) {
                    // Grab the id.
                    $sql2 = 'SELECT aisle_id FROM aisle ORDER BY aisle_id DESC LIMIT 1';
                    $aisle_id = get_data($con, $sql2, '')[0]['aisle_id'];

                    $sql3 = 'INSERT INTO call_range (collection, call_start, call_end, side, aisle)
                             VALUES (?, ?, ?, ?, ?)';

                    foreach ($aisle['call_ranges'] as $call_range) {
                        $side = 0;

                        if (array_key_exists('side', $call_range)) {
                            $side = $call_range['side'];
                        }

                        run_query($con, $sql3, 'sssii', 
                            $call_range['collection'], 
                            $call_range['call_start'], $call_range['call_end'], 
                            $side, $aisle_id);
                    }
                }
            }
        }

        // Add aisle areas into the database.
        if (array_key_exists('aisle_areas', $floor)) {
            $sql = 'INSERT INTO aisle_area (center_x, center_y, width, height, rotation, floor)
                    VALUES (?, ?, ?, ?, ?, ?)';

            foreach ($floor['aisle_areas'] as $aisle_area) {
                run_query($con, $sql, 'dddddi', $aisle_area['center_x'], 
                    $aisle_area['center_y'], $aisle_area['width'],
                    $aisle_area['height'], $aisle_area['rotation'], $floor_id);

                if (array_key_exists('aisles', $aisle_area)) {
                    // Grab the id.
                    $sql2 = 'SELECT aisle_area_id FROM aisle_area ORDER BY aisle_area_id DESC LIMIT 1';
                    $aisle_area_id = get_data($con, $sql2, '')[0]['aisle_area_id'];


                    $sql3 = 'INSERT INTO aisle (center_x, center_y, width, height, rotation, is_double_sided, aisle_area, floor)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?)';

                    foreach ($aisle_area['aisles'] as $aisle) {
                        run_query($con, $sql3, 'dddddiii', $aisle['center_x'], 
                            $aisle['center_y'], $aisle['width'], 
                            $aisle['height'], $aisle['rotation'], 
                            intval($aisle['is_double_sided']), $aisle_area_id,
                            $floor_id);

                        // Insert call ranges.
                        if (array_key_exists('call_ranges', $aisle)) {
                            // Grab the id.
                            $sql4 = 'SELECT aisle_id FROM aisle ORDER BY aisle_id DESC LIMIT 1';
                            $aisle_id = get_data($con, $sql4, '')[0]['aisle_id'];

                            $sql5 = 'INSERT INTO call_range (collection, call_start, call_end, side, aisle)
                                     VALUES (?, ?, ?, ?, ?)';

                            foreach ($aisle['call_ranges'] as $call_range) {
                                $side = 0;

                                if (array_key_exists('side', $call_range)) {
                                    $side = $call_range['side'];
                                }

                                run_query($con, $sql5, 'sssii', 
                                    $call_range['collection'], 
                                    $call_range['call_start'], 
                                    $call_range['call_end'], $side, $aisle_id);
                            }
                        }
                    }
                }
            }
        }

        // Close database.
        $con->close();

        // Send response.
        $response['success'] = TRUE;

        echo json_encode($response);
    }


    /**
     This updates a floor's name and floor order within a library.

     POST parameters:
     'token':       Access token given from login.
     'floor_id':    Id of the floor to perform the change to.
     'floor_name':  New name of the floor.
     'floor_order': New order of the floor.

     Response format:
     'success':         Whether update was successful.
     'error':           The error message, if not successful.
     */
    function update_floor_meta() {
        $con = connect();

        // Check user credentials.
        check_token($con, $_POST['token']);

        // Parse parameters.
        $new_name = $_POST['floor_name'];
        $new_order = $_POST['floor_order'];
        $floor_id = $_POST['floor_id'];

        // Verify the floor is valid.
        $sql = 'SELECT * FROM floor WHERE floor_id = ?';
        $result = get_data($con, $sql, 'i', $floor_id);

        if (count($result) != 1) {
            error('update_floor: Given floor is not found.');
        }

        // Update floor meta information.
        $sql = 'UPDATE floor SET floor_name = ?, floor_order = ? WHERE floor_id = ?';
        $run_query($con, $sql, 'si', $new_name, $new_order, $floor_id);

        // Close database.
        $con->close();

        // Send response.
        $response['success'] = TRUE;

        echo json_encode($response);
    }


    /**
     This deletes a library and all floors associated with it. Deletions are not 
     recoverable.

     POST parameters:
     'token':       Access token given from login.
     'library_id':  Id of the library to delete.

     Response format:
     'success':     Whether deletion was successful.
     'error':       The error message, if not successful.
     */
    function delete_library() {
        $con = connect();

        // Check user credentials.
        check_token($con, $_POST['token']);

        // Parse parameters.
        $library_id = $_POST['library_id'];

        // Verify the floor is valid.
        $sql = 'SELECT * FROM library WHERE library_id = ?';
        $result = get_data($con, $sql, 'i', $library_id);

        if (count($result) != 1) {
            error('delete_library: Given library is not found.');
        }

        // Delete library.
        $sql = 'DELETE FROM library WHERE library_id = ?';
        $run_query($con, $sql, 'i', $library_id);

        // Close database.
        $con->close();

        // Send response.
        $response['success'] = TRUE;

        echo json_encode($response);
    }


    /**
     This deletes a floor and all objects inside it. Deletions are not 
     recoverable.

     POST parameters:
     'token':       Access token given from login.
     'floor_id':    Id of the floor to delete.

     Response format:
     'success':     Whether deletion was successful.
     'error':       The error message, if not successful.
     */
    function delete_floor() {
        $con = connect();

        // Check user credentials.
        check_token($con, $_POST['token']);

        // Parse parameters.
        $floor_id = $_POST['floor_id'];

        // Verify the floor is valid.
        $sql = 'SELECT * FROM floor WHERE floor_id = ?';
        $result = get_data($con, $sql, 'i', $floor_id);

        if (count($result) != 1) {
            error('update_floor: Given floor is not found.');
        }

        // Delete floor.
        $sql = 'DELETE FROM floor WHERE floor_id = ?';
        $run_query($con, $sql, 'i', $floor_id);

        // Close database.
        $con->close();

        // Send response.
        $response['success'] = TRUE;

        echo json_encode($response);
    }


    //////////////////////
    // HELPER FUNCTIONS //
    //////////////////////

    /**
     Validates floor according to the specified JSON format. May report an error 
     if anything is not in the right format.

     Arguments:
     $floor:    The floor that requires validation.

     Return value:
     Nothing if valid, an exception otherwise.
     */
    function validate_floor($floor) {
        if (array_key_exists('aisles', $floor)) {
            // Parse all aisles
            foreach ($floor['aisles'] as $aisle) {
                validate_aisle($aisle);
            }
        }

        if (array_key_exists('aisle_areas', $floor)) {
            // Parse all aisle areas
            foreach ($floor['aisle_areas'] as $aisle_area) {
                validate_aisle_area($aisle_area);
            }
        }

        if (array_key_exists('landmarks', $floor)) {
            // Parse all landmarks
            foreach ($floor['landmarks'] as $landmark) {
                validate_landmark($landmark);
            }
        }

        if (array_key_exists('walls', $floor)) {
            // Parse all walls
            foreach ($floor['walls'] as $wall) {
                validate_wall($wall);
            }
        }

        if (!array_key_exists('floor_id', $floor) || !is_int($floor['floor_id'])) {
            error('validate_floor: incorrect or missing floor_id from floor object.');
        }

        if (!array_key_exists('floor_order', $floor) || !is_numeric($floor['floor_order'])) {
            error('validate_floor: incorrect or missing floor_order from floor object.');
        }
    }

    /**
     Validates call number range according to the specified JSON format. Throws
     an exception if the call numbers within the range are not in the right 
     format.

     Arguments:
     $call_range: The call number range that requires validation.

     Return value:
     Nothing if valid, exception otherwise.
     */
    function validate_call_range($call_range) {
        // First check if all keys exist, then check if the resulting call
        // numbers are valid.
        if (array_key_exists('call_start', $call_range) && 
            is_string($call_range['call_start']) &&
            array_key_exists('call_end', $call_range) && 
            is_string($call_range['call_end']) &&
            !(array_key_exists('collection', $call_range) && 
                !is_string($call_range['collection'])) &&
            !(array_key_exists('side', $call_range) && 
                !is_string($call_range['side']) && 
                !is_numeric($call_range['side']))) {
            // We parse the numbers through convert_call_number. An error will
            // be thrown if there are any errors.
            $c1 = convert_call_number($call_range['call_start']);
            $c2 = convert_call_number($call_range['call_start']);

            if (compare_call_numbers($c1, $c2) >= 0) {
                error('validate_call_range: call_start is bigger than call_end.');
            }
        } else {
            error('validate_call_range: incorrect or missing keys from call_range object.');            
        }
    }


    /**
     Validates aisle according to the specified JSON format. May report an error 
     if any call number within the ranges is not in the right format.

     Arguments:
     $aisle:    The aisle that requires validation.

     Return value:
     TRUE if valid, FALSE or exception otherwise.
     */
    function validate_aisle($aisle) {
        // First check if all keys exist, then check if the resulting call
        // numbers are valid.
        if (validate_rect($aisle) &&
            array_key_exists('is_double_sided', $aisle) && 
            is_bool($aisle['is_double_sided'])) {

            if (array_key_exists('call_ranges', $aisle)) {
                foreach ($aisle['call_ranges'] as $call_range) {
                    validate_call_range($call_range);
                }
            }
        } else {
            error('validate_aisle: incorrect or missing keys from aisle object.');
        }
    }


    /**
     Validates aisle area according to the specified JSON format. May report an
     error if any call number within the ranges within the aisles is not in the 
     right format.

     Arguments:
     $aisle_area:   The aisle area that requires validation.

     Return value:
     TRUE if valid, FALSE or exception otherwise.
     */
    function validate_aisle_area($aisle_area) {
        // First check if all keys exist, then check if the resulting call
        // numbers are valid.
        if (validate_rect($aisle_area)) {
            $is_valid = TRUE;

            if (array_key_exists('aisles', $aisle_area)) {
                foreach ($aisle_area['aisles'] as $aisle) {
                    validate_aisle($aisle);
                }
            }
        } else {
            error('validate_aisle_area: incorrect or missing keys from aisle_area object.');
        }
    }


    /**
     Validates landmark according to the specified JSON format.

     Arguments:
     $landmark: The landmark that requires validation.

     Return value:
     TRUE if valid, FALSE otherwise.
     */
    function validate_landmark($landmark) {
        validate_rect($landmark);

        if (!array_key_exists('landmark_type', $landmark) || 
            !is_string($landmark['landmark_type'])) {
            error('validate_landmark: incorrect or missing keys from landmark object.');
        }
    }


    /**
     Validates a wall according to the specified JSON format.

     Arguments:
     $wall: The wall object that requires validation.

     Return value:
     TRUE if valid, FALSE otherwise.
     */
    function validate_wall($wall) {
        if (!(array_key_exists('start_x', $wall) && 
            is_numeric($wall['start_x']) && 
            array_key_exists('start_y', $wall) && 
            is_numeric($wall['start_y']) &&
            array_key_exists('end_x', $wall) && 
            is_numeric($wall['end_x']) &&
            array_key_exists('end_y', $wall) &&
            is_numeric($wall['end_y']))) {
            error('validate_wall: incorrect or missing keys from wall object.');
        }
    }


    /**
     Validates rectangle (which underlies all of our geometry apart from walls)
     according to the specified JSON format. This checks whether the given
     object has 'center_x', 'center_y', 'width', 'height' and 'rotation' keys
     with appropriate types.

     Arguments:
     $rect: The object that requires validation.

     Return value:
     TRUE if valid, FALSE otherwise.
     */
    function validate_rect($rect) {
        if (!(array_key_exists('center_x', $rect) && 
            is_numeric($rect['center_x']) && 
            array_key_exists('center_y', $rect) && 
            is_numeric($rect['center_y']) &&
            array_key_exists('width', $rect) && 
            is_numeric($rect['width']) &&
            array_key_exists('height', $rect) &&
            is_numeric($rect['height']) &&
            array_key_exists('rotation', $rect) &&
            is_numeric($rect['rotation']))) {
            error('validate_rect: incorrect or missing keys from rect object.');
        }
    }


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
        $result = preg_match('(^[A-Z]+)', $call_number, $matches);

        if ($result === FALSE) {
            error('covert_call_number: Regular expression matching failed.');
        } else if ($result === 0) {
            error('covert_call_number: Call number format error. Expected class.');
        }

        $class = $matches[0];

        // Find subclass
        $result = preg_match('([0-9]*\.?[0-9]+)', $call_number, $matches);

        if ($result === FALSE) {
            error('covert_call_number: Regular expression matching failed.');
        } else if ($result === 0) {
            // We should have no more parts.
            return $class;
        }

        $subclass = $matches[0];

        // Cutters
        $result = preg_match_all('(\.[a-zA-Z]+[0-9]+)', $call_number, $matches);

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
        $result = preg_match('([a-zA-Z]+)', $cutter, $matches);

        if ($result === FALSE) {
            error('split_cutter_number: Regular expression matching failed.');
        } else if ($result === 0) {
            error('split_cutter_number: Cutter number format error.');
        }

        $splitted[0] = $matches[0];

        $result = preg_match('([0-9]+)', $cutter, $matches);

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
        $cmp1 = preg_split('( )', convert_call_number($c1));
        $cmp2 = preg_split('( )', convert_call_number($c2));

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
