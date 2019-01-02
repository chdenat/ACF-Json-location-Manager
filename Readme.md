# ACF-Json-location-manager
ACF companion class to help developers to manage the json file for syncronisation

The developer dilemna with ACF...

When a developer uses ACF, he can manage in a VCS like git or subversion all the field groups he work with.
For this, he can add a json description of his field group and push it on his repo and the push it on  its production  site for example. 
Then using the synchronization he can synchronized the changes on the production site then write new awesome piece of code...
- local Json : https://www.advancedcustomfields.com/resources/local-json/
- Json Sync : https://www.advancedcustomfields.com/resources/synchronized-json/

But (there's always a but, even in a fairy tale), he can only define one json location... And if the developer is working on several plugins or themes, all json are mixed and for delivery, it will be really annoying.

*ACF Json location Manager* is  class that add a new location rule for each filed group and using this, the developer can decide where he want to put it's json.

Available locations are :
- Activated plugins
- Themes (parent and/or child)

The only thing to do is to create , in each theme or plugin he wants, an acf-json directory and the new location will be displayed as a new choice in the rule.

Of course, on thos files are pushed on production, it will be possible to synchronize them as the standard way and used them.

ACF is a great tool and its creator, Eliott Condon, has put a lot of hooks in the code and this tool is using some of them to achieve the job.
