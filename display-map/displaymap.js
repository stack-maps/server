/*
This JavaScript file is used for creating a popup window to display a map of a library.
The flow of the functions is as follow:

Call function display_map with appropriate input parameters ->

It will send a request to assigned destination with the parameters ->

After receiving the response, determine if valid then continue, else stop with an alert()

    (In case of continue)
    Check if the device is a mobile or not

        (If mobile)
        Create new window and call function draw_map_in_new_window with appropriate input parameters

        (If not mobile)
        Call function draw_map with appropriate input parameters


All other functions are just helper functions. More details of them can be found at each's specification above them.
This JavaScript file should be working properly when the html and body have width and height of 100%
*/


/*
The following two d3 selection is just statements used to make sure the html and body 's width and height are 100%.
They may not be necessary with the page is already at that state.
 */
d3.select("html")
    .style("width", "100%")
    .style("height", "100%");

d3.select("body")
    .style("width", "100%")
    .style("height", "100%")
    .style("margin", 0);




/*
 The following variables are some of the display settings for the popup map

 display_window_margin:
 It refers the margin space of the popup window leaving at the top and bottom

 library_name_background_color:
 It refers to the color of the space on top of the map where contains the name and the call number

 wall_color:
 It refers to the color of the wall of shown in the map

 selected_stack_color:
 It refers to the color of the aisle when it is highlighted

 non_selected_stack_color:
 It refers to the color of the aisle when it is not highlighted

 icon_background_color:
 It refers to the color of the icons(such as Restroom, Elevator or Stairs)
 */
var display_window_margin = 40;
var library_name_background_color = "linear-gradient(#69CFD2, #6BDFCF, #6BEACF)";
var wall_color = "#BDD1D1";
var selected_stack_color = "#FB7A88";
var non_selected_stack_color = "#DDEEEE";
var icon_background_color = "#A3D7CF";



/*
 This is a statement adding the type of font that the popup window will use when called later
 */
d3.select("head").append("link")
    .attr("href", "https://fonts.googleapis.com/css?family=Open+Sans")
    .attr("rel", "stylesheet");

/*
 This is a function that checks whether the device in use is a mobile or not, by the method of checking
 the screen.width, screen.height, window.innerWidth and window.innerHeight.

 true means the device in use is a mobile
 false means the device in use is not a mobile

 Return: boolean
 */
function is_phone(){
    if(screen.width < 500 || screen.height < 500 || window.innerWidth < 500 || window.innerHeight < 500){
        return true
    }
    else{
        return false
    }
}


/*
 This is the main function that should be called by others. It takes in two string input parameters, and send the
 request to the server api with the parameters through AJAX.
 It will call draw_map to draw the map according to the response replied from the api in a popup in current window;
 or call draw_map_in_new_window to draw the map according to the response replied from the api in a new window,
 base on the boolean returned by is_phone.
 It will stop with an alert if the location of the book cannot be found by the api or an error is returned by the api.

 Input parameters:
 libname: String refering to the name of the library to be searched(such as "Uris")
 callno: String


 Return: null
 */
function display_map(libname, callno){
    libname = d3.select("#lib_name").node().value;
    callno = d3.select("#call_num").node().value;


    var xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            // document.getElementById("demo").innerHTML = this.responseText;
            console.log(this.responseText);
            console.log(JSON.parse(this.responseText));
            if(JSON.parse(this.responseText)["error"] != null){
                window.alert("The book is not found!");
            }
            else{
                if(is_phone()){
                    var myWindow = window.open("", "_blank");

                    var body = d3.select(myWindow.document.body).style("margin", 0);

                    draw_map_in_new_window(body, libname, callno, JSON.parse(this.responseText));
                }
                else{
                    draw_map(libname, callno, JSON.parse(this.responseText));
                }
            }
        }
    };
    xhttp.open("POST", "api.php", true);
    xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhttp.send("request=get_book_location&call_number=" + callno + "&library_name=" + libname);
}

/*
 This is a boolean variable that determines if the map is currently displaying.

 true if the map is displaying
 false if the map is not displaying

 default: false
 */
var map_display = false;

/*
 This is the function that create a popup window in the current window and draw the map inside the popup.
 It will only perform if the map is not already displaying, determined by the value of map_display.

 It takes in three parameters, two strings and an object.
 It will altered the content of the object to fit for its use later by using the function reformat_data
 Then it will create the half transparent black background first
 Then create the popup window on top of it.
 It will add divs for the title of the popup and the svg for the map shown.
 The dimension of the popup is determined here, by the content in the object, adjusted_result
 The actual drawings of the map are done by other helper functions.
 After all the drawings are done, move the popup window to appear as the center
 and change the state of map_display to true, indicating the map is currently shown

 The only way to remove the popup now is by clicking at the half transparent background, outside of the popup window div

 Input parameters:

 libname: String
 callno: String
 return_result:
 Object {

         }


 Return: null
 */
function draw_map(libname, callno, return_result) {
    if(map_display == false){
        var adjusted_result = reformat_data(return_result);

        var background = d3.select("body")
            .append("div")
            .style("position", "fixed")
            .style("top", 0)
            .style("left", 0)
            .style("width", "100%")
            .style("height", "100%")
            .style("background-color", "rgba(0, 0, 0, 0.5)")
            .style("overflow", "auto");

        background.on("click", function(){
            background.node().remove();
            map_display = false;
        });

        var display_window = background.append("div")
            .style("position", "absolute")
            .style("width", "80%")
            .style("height", "80%")
            .style("margin", display_window_margin + "px")
            .style("background-color", "white")
            .style("font-family", "'Open Sans', sans-serif")
            .on("click", function(){d3.event.stopPropagation();});

        var title_bar_div = display_window.append("div")
            .style("color", "white")
            .style("background", library_name_background_color)
            .style("padding", "10px")
            .style("text-align", "center");

        var library_name_div = title_bar_div.append("div")
            .style("padding", "10px")
            .style("font-size", "38px")
            .text("Floor " + adjusted_result["floors"][0]["floor_name"] + " " + libname + " Library");

        var call_num_div = title_bar_div.append("div")
            .style("padding", "10px")
            .style("font-size", "23px")
            .text("Call Number: " + callno);

        var input_dimension = calc_svg_dimension(adjusted_result["floors"][0]);
        var raw_width = input_dimension["x_max"] - input_dimension["x_min"];
        var raw_height = input_dimension["y_max"] - input_dimension["y_min"];
        var adjusted_width = Math.ceil(raw_width / 100) * 100;
        var adjusted_height = Math.ceil(raw_height / 100) * 100;


        var svg_div = display_window.append("div")
            .style("padding", "10px")
            .style("width", "calc(100% - 20px)")
            .style("height", "calc(100% - 163px)");
        var svg = svg_div.append("svg")
            .attr("viewBox", (input_dimension["x_min"] - 30) + " "+ (input_dimension["y_min"] - 10) + " " + adjusted_width + " " + adjusted_height)
            .attr("width", "100%")
            .attr("height", "100%");


        draw_walls(svg, adjusted_result["floors"][0]["walls"]);
        draw_stacks(svg, adjusted_result["floors"][0]["aisles"]);
        draw_icons(svg, adjusted_result["floors"][0]["landmarks"]);
        highlight_stack(svg, adjusted_result["aisles"]);







        display_window
            .style("left", Math.max((background.node().getBoundingClientRect().width - display_window.node().getBoundingClientRect().width - display_window_margin) / 2, 0) + "px");

        map_display = true;
    }
}

/*
 It is a function that takes a floor object and returns the extreme coordinates of the dimension of the walls, aisles
 and landmarks
 In this scenario it should take in the walls list of the JSON object, as there should not be any objects existing
 outside of the walls, so the dimension of the walls should be the dimension of the svg


 Input parameters:

 floor_object:
 JSON floor object

 Return: Object {x_min: float, y_min: float, x_max: float, y_max: float}
 */
function calc_svg_dimension(floor_object){
    var x_min = 0, y_min = 0, x_max = 0, y_max = 0;
    var set = false;
    var i;

    for(i = 0; i < floor_object["walls"].length; i++){
        if(set == false){
            x_min = Math.min(floor_object["walls"][i]["start_x"], floor_object["walls"][i]["end_x"]);
            x_max = Math.max(floor_object["walls"][i]["start_x"], floor_object["walls"][i]["end_x"]);
            y_min = Math.min(floor_object["walls"][i]["start_y"], floor_object["walls"][i]["end_y"]);
            y_max = Math.max(floor_object["walls"][i]["start_y"], floor_object["walls"][i]["end_y"]);
            set = true;
        }
        else {
            x_min = Math.min(x_min, floor_object["walls"][i]["start_x"], floor_object["walls"][i]["end_x"]);
            x_max = Math.max(x_max, floor_object["walls"][i]["start_x"], floor_object["walls"][i]["end_x"]);
            y_min = Math.min(y_min, floor_object["walls"][i]["start_y"], floor_object["walls"][i]["end_y"]);
            y_max = Math.max(y_max, floor_object["walls"][i]["start_y"], floor_object["walls"][i]["end_y"]);
        }
    }

    for(i = 0; i < floor_object["aisles"].length; i++){
        if(set == false){
            x_min = calc_corner_x(floor_object["aisles"][i]["center_x"], floor_object["aisles"][i]["width"], floor_object["aisles"][i]["height"], floor_object["aisles"][i]["rotation"])["min"];
            x_max = calc_corner_x(floor_object["aisles"][i]["center_x"], floor_object["aisles"][i]["width"], floor_object["aisles"][i]["height"], floor_object["aisles"][i]["rotation"])["max"];
            y_min = calc_corner_y(floor_object["aisles"][i]["center_y"], floor_object["aisles"][i]["width"], floor_object["aisles"][i]["height"], floor_object["aisles"][i]["rotation"])["min"];
            y_max = calc_corner_y(floor_object["aisles"][i]["center_y"], floor_object["aisles"][i]["width"], floor_object["aisles"][i]["height"], floor_object["aisles"][i]["rotation"])["max"];
            set = true;
        }
        else {
            x_min = Math.min(x_min, calc_corner_x(floor_object["aisles"][i]["center_x"], floor_object["aisles"][i]["width"], floor_object["aisles"][i]["height"], floor_object["aisles"][i]["rotation"])["min"]);
            x_max = Math.max(x_max, calc_corner_x(floor_object["aisles"][i]["center_x"], floor_object["aisles"][i]["width"], floor_object["aisles"][i]["height"], floor_object["aisles"][i]["rotation"])["max"]);
            y_min = Math.min(y_min, calc_corner_y(floor_object["aisles"][i]["center_y"], floor_object["aisles"][i]["width"], floor_object["aisles"][i]["height"], floor_object["aisles"][i]["rotation"])["min"]);
            y_max = Math.max(y_max, calc_corner_y(floor_object["aisles"][i]["center_y"], floor_object["aisles"][i]["width"], floor_object["aisles"][i]["height"], floor_object["aisles"][i]["rotation"])["max"]);
        }
    }

    for(i = 0; i < floor_object["landmarks"].length; i++){
        if(set == false){
            x_min = calc_corner_x(floor_object["aisles"][i]["center_x"], floor_object["landmarks"][i]["width"], floor_object["landmarks"][i]["height"], floor_object["landmarks"][i]["rotation"])["min"];
            x_max = calc_corner_x(floor_object["aisles"][i]["center_x"], floor_object["landmarks"][i]["width"], floor_object["landmarks"][i]["height"], floor_object["landmarks"][i]["rotation"])["max"];
            y_min = calc_corner_y(floor_object["aisles"][i]["center_y"], floor_object["landmarks"][i]["width"], floor_object["landmarks"][i]["height"], floor_object["landmarks"][i]["rotation"])["min"];
            y_max = calc_corner_y(floor_object["aisles"][i]["center_y"], floor_object["landmarks"][i]["width"], floor_object["landmarks"][i]["height"], floor_object["landmarks"][i]["rotation"])["max"];
            set = true;
        }
        else {
            x_min = Math.min(x_min, calc_corner_x(floor_object["landmarks"][i]["center_x"], floor_object["landmarks"][i]["width"], floor_object["landmarks"][i]["height"], floor_object["landmarks"][i]["rotation"])["min"]);
            x_max = Math.max(x_max, calc_corner_x(floor_object["landmarks"][i]["center_x"], floor_object["landmarks"][i]["width"], floor_object["landmarks"][i]["height"], floor_object["landmarks"][i]["rotation"])["max"]);
            y_min = Math.min(y_min, calc_corner_y(floor_object["landmarks"][i]["center_y"], floor_object["landmarks"][i]["width"], floor_object["landmarks"][i]["height"], floor_object["landmarks"][i]["rotation"])["min"]);
            y_max = Math.max(y_max, calc_corner_y(floor_object["landmarks"][i]["center_y"], floor_object["landmarks"][i]["width"], floor_object["landmarks"][i]["height"], floor_object["landmarks"][i]["rotation"])["max"]);
        }
    }



    function calc_corner_x(center_x, width, height, rotation){
        //+w+h
        var one = center_x + (Math.cos(rotation / 180 * Math.PI) * width / 2) - (Math.sin(rotation / 180 * Math.PI) * height / 2);

        //+w-h
        var two = center_x + (Math.cos(rotation / 180 * Math.PI) * width / 2) - (Math.sin(rotation / 180 * Math.PI) * height / 2 * -1);

        //-w-h
        var three = center_x + (Math.cos(rotation / 180 * Math.PI) * width / 2 * -1) - (Math.sin(rotation / 180 * Math.PI) * height / 2 * -1);

        //-w+h
        var four = center_x + (Math.cos(rotation / 180 * Math.PI) * width / 2 * -1) - (Math.sin(rotation / 180 * Math.PI) * height / 2);
        return {
            min: Math.min(one ,two, three, four),
            max: Math.max(one ,two, three, four)
        }
    }

    function calc_corner_y(center_y, width, height, rotation){
        //+w+h
        var one = center_y + (Math.sin(rotation / 180 * Math.PI) * width / 2) + (Math.cos(rotation / 180 * Math.PI) * height / 2);

        //+w-h
        var two = center_y + (Math.sin(rotation / 180 * Math.PI) * width / 2) + (Math.cos(rotation / 180 * Math.PI) * height / 2 * -1);

        //-w-h
        var three = center_y + (Math.sin(rotation / 180 * Math.PI) * width / 2 * -1) + (Math.cos(rotation / 180 * Math.PI) * height / 2 * -1);

        //-w+h
        var four = center_y + (Math.sin(rotation / 180 * Math.PI) * width / 2 * -1) + (Math.cos(rotation / 180 * Math.PI) * height / 2);
        return {
            min: Math.min(one ,two, three, four),
            max: Math.max(one ,two, three, four)
        }
    }


    return {
        x_min: x_min,
        y_min: y_min,
        x_max: x_max,
        y_max: y_max
    }
}

/*
 This is a function that takes two parameters, one d3 selection element and one array of objects. It will create line
 element according to the x1, y1, x2, y2 of each element in "object_list" with 3px width and color as the wall_color

 Input parameters:

 d3_svg: d3 selection element
 object_list:
 Array of Objects
 [{x1: float, y1: float, x2: float, y2: float}]

 Return: null
 */
function draw_walls(d3_svg, object_list){
    for(var i = 0; i < object_list.length; i++){
        d3_svg.append("line")
            .attr("x1", object_list[i]["start_x"])
            .attr("y1", object_list[i]["start_y"])
            .attr("x2", object_list[i]["end_x"])
            .attr("y2", object_list[i]["end_y"])
            .style("stroke-width", "3px")
            .style("stroke", wall_color);
    }
}

/*
 This is a function that takes two parameters, one d3 selection element and one array of objects. It will create a g
 element according to the center_x, center_y, roatation of each element in "object_list" then create one or two rect
 based on the value of sides of the element in "object_list" with the position of g as the center
 Input parameters:

 d3_svg: d3 selection element
 object_list:
 Array of Objects
 [{center_x: float, center_y: float, width: float, length: float, rotation: float, sides: int}]

 Return: null
 */
function draw_stacks(d3_svg, object_list){
    for(var i = 0; i < object_list.length; i++){

        var g = d3_svg.append("g")
            .attr("id", "aid" + object_list[i]["aisle_id"])
            .attr("transform", "rotate(" + object_list[i]["rotation"] + " " + object_list[i]["center_x"] + " " + object_list[i]["center_y"] + ")")
            .style("fill", non_selected_stack_color);

        if(object_list[i]["is_double_sided"]){
            g.append("rect")
                .attr("class", "side0")
                .attr("x", object_list[i]["center_x"] - (object_list[i]["width"] / 2))
                .attr("y", object_list[i]["center_y"] - (object_list[i]["height"] / 2))
                .attr("width", (object_list[i]["width"] / 2) - 2)
                .attr("height", (object_list[i]["height"]));
            g.append("rect")
                .attr("class", "side1")
                .attr("x", object_list[i]["center_x"] + 2)
                .attr("y", object_list[i]["center_y"] - (object_list[i]["height"] / 2))
                .attr("width", (object_list[i]["width"] / 2) - 2)
                .attr("height", (object_list[i]["height"]));
            // g.append("circle")
            //     .attr("cx", object_list[i]["center_x"])
            //     .attr("cy", object_list[i]["center_y"])
            //     .attr("r", 2)
            //     .style("fill", "black");
        }
        else{
            g.append("rect")
                .attr("x", object_list[i]["center_x"] - (object_list[i]["width"] / 2))
                .attr("y", object_list[i]["center_y"] - (object_list[i]["height"] / 2))
                .attr("width", object_list[i]["width"])
                .attr("height", object_list[i]["height"]);
        }
    }
}

/*
 This is a function that takes two parameters, one d3 selection element and one array of objects. It will create a rect
 element according to the center_x, center_y, width and length of each element in "object_list" then create an icon on
 top of it based on the lname of the element in "object_list" and scale its size according to the width and length
 Input parameters:

 d3_svg: d3 selection element
 object_list:
 Array of Objects
 [{center_x: float, center_y: float, width: float, length: float, lname: string}]

 Return: null
 */
function draw_icons(d3_svg, object_list){
    for(var i = 0; i < object_list.length; i++){
        var x = object_list[i]["center_x"] - (object_list[i]["width"] / 2);
        var y = object_list[i]["center_y"] - (object_list[i]["height"] / 2);
        var padding = 20;
        var scaling = Math.floor((Math.min(object_list[i]["width"], object_list[i]["height"]) - padding * 2) / 20);
        scaling = Math.max(scaling, 0);
        d3_svg.append("rect")
            .attr("x", x)
            .attr("y", y)
            .attr("width", object_list[i]["width"])
            .attr("height", object_list[i]["height"])
            .attr("transform", "rotate(" + object_list[i]["rotation"] + ")")
            .style("fill", icon_background_color);

        if(object_list[i]["landmark_type"] == "elevator"){
            d3_svg.append("path")
                .attr("d",  "M7,2L11,6H8V10H6V6H3L7,2M17,10L13,6H16V2H18V6H21L17,10M7,12H17A2,2 0 0,1 19,14V20A2,2 0 0,1 17,22H7A2,2 0 0,1 5,20V14A2,2 0 0,1 7,12M7,14V20H17V14H7Z")
                .attr("fill", "white")
                .attr("transform", "translate(" + (x + (object_list[i]["width"] - 24 * scaling) / 2) + ", " + (y + (object_list[i]["height"] - 24 * scaling) / 2) + ")scale(" + scaling + ")");
        }
        else if(object_list[i]["landmark_type"] == "toilet"){
            d3_svg.append("path")
                .attr("d",  "M7.5,2A2,2 0 0,1 9.5,4A2,2 0 0,1 7.5,6A2,2 0 0,1 5.5,4A2,2 0 0,1 7.5,2M6,7H9A2,2 0 0,1 11,9V14.5H9.5V22H5.5V14.5H4V9A2,2 0 0,1 6,7M16.5,2A2,2 0 0,1 18.5,4A2,2 0 0,1 16.5,6A2,2 0 0,1 14.5,4A2,2 0 0,1 16.5,2M15,22V16H12L14.59,8.41C14.84,7.59 15.6,7 16.5,7C17.4,7 18.16,7.59 18.41,8.41L21,16H18V22H15Z")
                .attr("fill", "white")
                .attr("transform", "translate(" + (x + (object_list[i]["width"] - 24 * scaling) / 2) + ", " + (y + (object_list[i]["height"] - 24 * scaling) / 2) + ")scale(" + scaling + ")");
        }
        else if(object_list[i]["landmark_type"] == "stairs"){
            d3_svg.append("path")
                .attr("d",  "M15,5V9H11V13H7V17H3V20H10V16H14V12H18V8H22V5H15Z")
                .attr("fill", "white")
                .attr("transform", "translate(" + (x + (object_list[i]["width"] - 24 * scaling) / 2) + ", " + (y + (object_list[i]["height"] - 24 * scaling) / 2) + ")scale(" + scaling + ")");
        }
    }
}

/*
 It is a function that take an object, which should come from the api with the JSON format, and changes the appropriate
 values in them so that the object can be used later for drawing the map correctly, and return the corrected object.
 Values in the new object are altered, but the input object is remained untouched
 All the y-related values are being * -1 because the coordinate system created by the editor is different from the one
 used by the svg, where their y-coordinates are reversed
 All the x-related values are being * 1 so as that make sure they are now numbers but not strings, so that calculations
 can be done on them in the later part
 Rotation is also being * -1 because they are rotating in different directions in different systems, and here is to
 correct them into the same direction

 Input parameters:

 input_data:
 JSON floor objects

 Return:
 Objects{

 }
 */
function reformat_data(input_data){
    var output_data = JSON.parse(JSON.stringify(input_data));
    var i, j;

    for(i = 0; i < output_data["floors"].length; i++){

        for(j = 0; j < output_data["floors"][i]["aisles"].length; j++){
            output_data["floors"][i]["aisles"][j]["center_x"] = 1 * output_data["floors"][i]["aisles"][j]["center_x"];
            output_data["floors"][i]["aisles"][j]["center_y"] = -1 * output_data["floors"][i]["aisles"][j]["center_y"];
            output_data["floors"][i]["aisles"][j]["rotation"] = -1 * output_data["floors"][i]["aisles"][j]["rotation"];

        }

        for(j = 0; j < output_data["floors"][i]["walls"].length; j++){
            output_data["floors"][i]["walls"][j]["start_x"] = 1 * output_data["floors"][i]["walls"][j]["start_x"];
            output_data["floors"][i]["walls"][j]["end_x"] = 1 * output_data["floors"][i]["walls"][j]["end_x"];
            output_data["floors"][i]["walls"][j]["start_y"] = -1 * output_data["floors"][i]["walls"][j]["start_y"];
            output_data["floors"][i]["walls"][j]["end_y"] = -1 * output_data["floors"][i]["walls"][j]["end_y"];
        }

        for(j = 0; j < output_data["floors"][i]["landmarks"].length; j++){
            output_data["floors"][i]["landmarks"][j]["center_x"] = 1 * output_data["floors"][i]["landmarks"][j]["center_x"];
            output_data["floors"][i]["landmarks"][j]["center_y"] = -1 * output_data["floors"][i]["landmarks"][j]["center_y"];
            output_data["floors"][i]["landmarks"][j]["rotation"] = -1 * output_data["floors"][i]["landmarks"][j]["rotation"];
        }

    }

    return output_data
}

/*
 This is a function that takes three parameters, one d3 selection element and two ints. It will select the element with
 the id "aid + id", then determine if multiple rect are inside this element. If yes, then select the one corresponding to
 the "side", else just select the only rect and change its color to selected_stack_color

 Input parameters:

 d3_svg: d3 selection element
 target_list:
 Array of objects
 [{aisle_id: int, side: int}]


 Return: null
 */
function highlight_stack(d3_svg, target_list){
    var i;
    for(i = 0; i < target_list.length; i++){
        var id = target_list[i]["aisle_id"];
        var side = target_list[i]["side"];
        if(!d3_svg.select("#aid" + id).empty()){
            if(d3_svg.select("#aid" + id).selectAll("rect").size() == 2){
                d3_svg.select("#aid" + id).select(".side" + side)
                    .style("fill", selected_stack_color);
            }
            else{
                d3_svg.select("#aid" + id).select("rect").style("fill", selected_stack_color);
            }
        }
    }
}

/*
 This is the function that create the divs in the new window and draw the map inside it.

 It takes in four parameters, one d3 selection element, two strings and an object.
 It will altered the content of the object to fit for its use later by using the function reformat_data
 Then it will create the divs inside the element specified by "body" parameter
 It will add divs for the title and the svg for the map shown.
 The dimension of the popup is determined here, by the content in the object, adjusted_result
 The actual drawings of the map are done by other helper functions.

 Input parameters:
 body: d3 selection element
 libname: String
 callno: String
 return_result:
 Object {

 }


 Return: null
 */
function draw_map_in_new_window(body, libname, callno, return_result){
    var adjusted_result = reformat_data(return_result);

    var display_window = body.append("div")
        .style("position", "absolute")
        .style("background-color", "white")
        .style("font-family", "'Open Sans', sans-serif")
        .on("click", function(){d3.event.stopPropagation();});

    var title_bar_div = display_window.append("div")
         .style("color", "white")
         .style("background", library_name_background_color)
         .style("padding", "10px")
         .style("text-align", "center");

    var library_name_div = title_bar_div.append("div")
        .style("padding", "10px")
        .style("font-size", "38px")
        .text("Floor " + adjusted_result["floors"][0]["floor_name"] + " " + libname + " Library");

    var call_num_div = title_bar_div.append("div")
        .style("padding", "10px")
        .style("font-size", "23px")
        .text("Call Number: " + callno);

    var input_dimension = calc_svg_dimension(adjusted_result["floors"][0]);
    var raw_width = input_dimension["x_max"] - input_dimension["x_min"];
    var raw_height = input_dimension["y_max"] - input_dimension["y_min"];
    var adjusted_width = Math.ceil(raw_width / 100) * 100 + 20;
    var adjusted_height = Math.ceil(raw_height / 100) * 100 + 20;



    var svg_div = display_window.append("div")
        .style("padding", "10px");
    var svg = svg_div.append("svg")
        .attr("viewBox", (input_dimension["x_min"] - 10) + " "+ (input_dimension["y_min"] - 10) + " " + adjusted_width + " " + adjusted_height)
        .attr("width", adjusted_width + "px")
        .attr("height", adjusted_height + "px");

    draw_walls(svg, adjusted_result["floors"][0]["walls"]);
    draw_stacks(svg, adjusted_result["floors"][0]["aisles"]);
    draw_icons(svg, adjusted_result["floors"][0]["landmarks"]);
    highlight_stack(svg, adjusted_result["aisles"]);
}


