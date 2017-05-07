# StackMaps - Server
This repository contains code to set up a server to manage and display where particular books in the library are. In essence, there are three parts: the database holding library floor information, a script for updating and querying the database, and a snippet to render and display the location of a particular book on a map in the browser.

Below are instructions on how to set up a working server.

## Database
This project uses MySQL database. While any remote MySQL database can work, for security concerns it is the best to have the MySQL database and the access script on the same server, and disallow any remote access on the database.

This [file]() contains SQL commands to set up necessary tables once such a database is created. For a break down on how the database is structured, refer to [this document]().

## Access API
The access API acts as the getter/setter of the database. It provides an extra layer of security as it can deny attacks on the database. For this project, the access API comes in the form of a php script. It will need host, port, username and password of the database. To set up the API, simply replace those information at the top of the file, then upload it to a server.

Custom security authorization logic should be added to this script to allow or deny database updating API calls. See the file for details on how to do so.

## Map
The map is what library patrons see when they click on a button. We wrap the map in a javascript file and provide a sample HTML page. To set this up, the javascript file should be uploaded to the same directory of wherever the actual MAP button is in the library catalogs, and in that HTML page, this javascript file should be imported, and the `displayMap(callno)` function should be called when the button is pressed.

## Map Editor
Once the server set up is complete, we need to create some library floor plans to actually use this system. Details of the map editor can be found in the accompanying repository [here](https://github.com/stack-maps/map-editor).
