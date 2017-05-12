function is_phone(){
    if(window.innerWidth < 500 || window.innerHeight < 500){
        return true
    }
    else{
        return false
    }
}

var myWindow;
function responsive_display(){
    console.log("1");
    if(is_phone()){
        console.log("2");
        myWindow = window.open("", "_blank");
    }
    else{
        console.log("3");
        display_map();
    }
}

function display_map() {
    var background = d3.select("body")
        .append("div")
        .style("position", "fixed")
        .style("top", 0)
        .style("left", 0)
        .style("width", window.innerWidth + "px")
        .style("height", window.innerHeight + "px")
        .style("background-color", "rgba(0, 0, 0, 0.5)")
        .style("overflow", "auto");

    background.on("click", function(){
        background.node().remove();
    });

    var display_window = background.append("div")
        .style("position", "absolute")
        .style("top", "10%")
        .style("left", "20%")
        .style("width", "60%")
        .style("padding", "20px")
        .style("margin", "20px")
        .style("background-color", "white");

    var text_div = display_window.append("div")
        .style("color", "grey");
    text_div.append("h1")
        .text("XXX Library: Level Y");
    text_div.append("p")
        .text("Call Number: ABCDEFG");

    display_window.append("svg")
        .attr("width", "100%")
        .attr("height", "800px")


}

