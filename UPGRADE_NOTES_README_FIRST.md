Aus Post v2.5.8d
=================
TO remove the old entries from the database the previouse version of the module MUST be uninstalled.
    
Upgrade Notes
-------------

If you have a previous version of AusPost Shipping installed:
Open Admin | Modules | Shipping Modules | Australia Post, click "Edit" and copy the API Key and save it somewhere.


Remove and un-install the previous version:
---------------------
    go to  Admin | Modules | Shipping Modules, 
    select "Australia Post", click "Remove Module", click again on "Remove Module"
    select "Australia Post International" click "Remove Module", click again on "Remove Module"
    
    go to Admin | Modules | Plugin Manager
    select "Australia Post", and click "Un-Install", click again  on "Un-Install"
    select "Australia Post", and click "Clean Up" , select the previous version and click "confirm"
    
    
Copy the directory zc_plugins/ AustraliaPost / V2.5.8d to your plugins folder

Install and configure the latest version
---------------------
    in Admin | Modules | Plugin Manager 
    select "Australia Post", and click "Install" . If you have a choice of versions, select V2.5.8c
    go to  Admin | Modules | Shipping Modules, select "Australia Post" and click "Install Module", enter the API key, check your settings and "Update"
    repeat for "Australia Post International" settings.