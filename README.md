# Retargeting Tracker Module For Magento 2

# Install

To install the Retargeting Tracker module for Magento 2 follow the instructions below:

1. Backup your Magento code and database before installing it.
2. Download Retargeting Extension installation package.
3. Upload contents of the Retargeting installation package to your store root directory/code/Retargeting/Tracker
4. Open SSH console of your server navigate to your store root folder:
    * cd path_to_the_store_root_folder
    * php bin/magento module:enable --clear-static-content Retargeting_Tracker
    * php bin/magento setup:upgrade
    * php bin/magento setup:di:compile
    * php bin/magento cache:flush
    * php bin/magento setup:static-content:deploy [<languages>]
    * php bin/magento cache:clean
5. Flush store cache. Log out from the backend and log in again.

**Make Sure the installation is done from under the FTP administrator account. Otherwise, make sure to set 775 permissions to the store root directory after the extension is deployed.**

---

**If you have a previous version of Retargeting Tracker Module installed, please uninstall and remove the previous version before installing this one.**

# Uninstall previous versions of Retargeting Tracker Module

1. Backup your Magento code and database before removing the module.
2. Open SSH console of your server navigate to your store root folder:
    * cd path_to_the_store_root_folder
    * php bin/magento module:disable --clear-static-content Retargeting_Tracker
    * php bin/magento module:uninstall Retargeting_Tracker
    * php bin/magento setup:upgrade
    * php bin/magento setup:di:compile
    * php bin/magento cache:flush
    * php bin/magento setup:static-content:deploy [<languages>]
3. Remove all Retargeting_Tracker module files and directories: `rm -rf app/code/Retargeting`
4. Flush store cache. Log out from the backend and log in again.

# This module was developed and tested on Magento:

* ver. 2.3.3
* ver. 2.3.4

**If your Magento version is not listed here, please test the module on local/dev/staging environment first!**

