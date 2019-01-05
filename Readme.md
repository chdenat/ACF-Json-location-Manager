# ACF-Json-location-manager
ACF companion class to help developers to manage the json files location (plugins and/or themes) for synchronization on different platforms.

##The developer dilemma with ACF...

When a developer is using  ACF with a theme or a plugin, he can manage (in a VCS like git or subversion) all the "Field groups" he works with.
For this, he should add a json description of his field group and push it on his repo. Then he could pull changes from his production  site for example. 

Then, using the sync mode, he can synchronized the changes on the production site. For further information please visit
- local Json : https://www.advancedcustomfields.com/resources/local-json/
- Json Sync : https://www.advancedcustomfields.com/resources/synchronized-json/

But (there's always a but, even in a fairy tale), he can only define one json location... And if the developer is working on several plugins or themes, all json will be mixed and for delivery, it will be really annoying.

##My solution : ACF Json location Manager

*ACF Json location Manager* is a class that adds a new location rule for each "Field group" and using this, the developer can decide where he wants to put his json.

Available locations are :
- Activated plugins
- Themes (parent and/or child)

The only thing to do is to create , in each theme or plugin he wants, an acf-json directory and the new location will be displayed as a new choice in the rule.

Of course, when those files are pushed on production, it will be possible to synchronize them using the standard 'ACF sync' way.

ACF is a great tool and its creator, Eliott Condon, has put a lot of hooks in the code and this tool is using some of them to achieve the job.
