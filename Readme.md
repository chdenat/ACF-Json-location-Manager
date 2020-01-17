# ACF-Json-location-manager
ACF companion class to help developers to manage the json files location (plugins and/or themes) for synchronization 
on different platforms.

- Version 1.1

## The developer dilemma with ACF...

When a developer is working on a theme or a plugin using ACF, he can manage (in a VCS like git or subversion) all the 
"Field groups" he works with. By adding ACF JSON Sync, he's able to add the field group JSON desc file to his repo 
and manage it using push/pull commands.

Then, using the sync mode available in ACF backend, he can synchronized the changes on the production site.
For further information please visit
- local Json : https://www.advancedcustomfields.com/resources/local-json/
- Json Sync : https://www.advancedcustomfields.com/resources/synchronized-json/

But (there's always a but, even in a fairy tale), he can only define one json location... When developing one theme 
or one plugin, no problem, but when he is developing both in the same time (I always add a plugin companion for my 
themes), it could be a problem.

With only one JSON file, the plugin and theme group fields will be mixed in one place, ACF for themes and ACF for 
plugins... :( Really annoying if the developer maintains one repo for plugin, one for theme.

## My solution : ACF Json location Manager

**ACF Json location Manager** is a class that adds the possibility, for each field group, to select the json file
 location. Then, the developer can decide where he wants to put his json. When a Json file is pulled on a specific 
 environment (staging, prod,...) it will be possible to synchronize it with the existing one, or add it if it is
 a new one.

Available locations are :
- Activated plugins
- Themes (parent and/or child)

The only thing to do is to create, in the required theme or plugin, a location directory and this new location will
 be displayed as a new choice in Json Sync metabox in group edit screen..

When those files will be pushed on another platform (staging or production), it is  possible to synchronize them 
using the standard `ACF synchronize` mode.

## How it works 
ACF is a great tool for wordpress and its creator, `Eliott Condon`, has inserted a lot of hooks in the code and 
AJLM tool is using some of them to achieve the job.

- During the load process, for syncronisation, AJLM scans all the possible directories (themes or plugins) to check
 if there is a location directory. All the json files are the copied to a specific directory, used by the
  "load-json" ACF hook. This directory stands in theme/uploads directory, under a directory called ajlm by default 
  (itt is possible to use another name, see below).
- During the save process, AJSLM will checks the location bound to the Field group using 'save-json' ACF Hook. 
Then ACF will save the json file in the right place.

## How to use it ?

### Deploying Code

- Download the `acf-json-location-manager.php` file and put it somewhere.
- include it in your theme using `functions.php` or in your plugin using require/include.
``` PHP
require_once '<the-right-place>/ACF_json_location_manager.php';

```
- init AJLM.
```PHP
ACF_json_location_manager::init();
```
### Settings

By default, AJLM will use :
```PHP
'json-dir'  => 'acf-json',  // name of the json location in each plugin/theme
'load-json' => 'ajlm',      // Dir used for loading jsons
```
but it is possible to use your own settings by specifying some parameters during the init call.
```PHP
ACF_json_location_manager::init([
    'json-dir'  => 'my-json', 
    'load-json' => 'all-jsons',
]);
```
### Creating directories
Create an `json-dir` directory (`acf-json` if you do not use your own settings) in the plugins or themes you want 
to save your json files, if they are not existing. And it's all !! (the `load-json` will be automatically created !


### Using AJLM

In the Field group Edition screen, you have now a `new meta box` used to select the associated json file.

![Meta Box 1](./docs/meta-1.png) ![Meta Box 2](./docs/meta-2.png)

If you want to move a json file from one location to another just select the new location in the metabox,
the old one will be deleted.



## Version log

### 1.1  (2020/01/17): 
- add : external settings
- change : code structure, variable and method names
- add : more explanation in the Readme.
              
### 1.0  (2020/01/15): 
- first 'official' release 

## Requirements
- ACF release 5.8 (not tested under)
- PHP 7.2
