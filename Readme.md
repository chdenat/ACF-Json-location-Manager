# ACF-Json-location-manager
ACF companion class to help developers to manage the json files location (plugins and/or themes) for synchronization on different platforms.

- Version 1.0

## The developer dilemma with ACF...

When a developer is working on a theme or a plugin using ACF, he can manage (in a VCS like git or subversion) all the "Field groups" he works with.
By adding ACF JSON Sync, he's able to add the field group JSON desc file to his repo and manage it using push/pull commands.

Then, using the sync mode available in ACF backend, he can synchronized the changes on the production site.
For further information please visit
- local Json : https://www.advancedcustomfields.com/resources/local-json/
- Json Sync : https://www.advancedcustomfields.com/resources/synchronized-json/

But (there's always a but, even in a fairy tale), he can only define one json location... When developing one theme or one plugin, no problem, but when he is developing both in the same time (I always add a plugin companion for my themes), it could be a problem.
With only one JSON file, the plugin and theme group fields will be mixed in one place, ACF for themes and ACF for plugins... :(
Really annoying if the developer maintains one repo for plugin, one for theme.

## My solution : ACF Json location Manager

**ACF Json location Manager** is a class that adds the possibility, for each field group, to select the json file location.
 Then, thee developer can decide where he wants to put his json. Whhen a Json file is pulled on an environment (staging, prod,...) it will be possible to synchronize it with the existing one, or add it it it a new one.

Available locations are :
- Activated plugins
- Themes (parent and/or child)

The only thing to do is to create, in the required theme or plugin, called "acf-json" and this new location will be displayed as a new choice in Json Sync metabox in group edit screen..

When those files will be pushed on another platform (staging or production), it is  possible to synchronize them using the standard `ACF synchronize` mode.

ACF is a great tool for wordpress and its creator, `Eliott Condon`, has inserted a lot of hooks in the code and AJLM tool is using some of them to achieve the job.

## How to use it ?

* Download the acf-json-location-manager.php file
* include it in your theme using `functions.php` or in your plugin.
* create an `acf-json` directory in your plugins or themes if they are not existing. And it's all ! 
![Meta Box 1](./docs/meta-1.png)
![Meta Box 2](./docs/meta-2.png)

In the Field group Edition screen, you will have a `new meta box` used to select the associated json file.

If you want to move a json file from one location to another just select the new location in the metabox,
the old one will be deleted.

##Version log :
- 1.0  (2020/01/15): first 'official' release 

