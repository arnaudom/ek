EK management tools suite : an attempt to build a full back-office (often called ERP) system with Drupal.

Note: although this application is been used and tested, this code has not yet completed a best practices or security review. We take no responsibility for any loss you may incur through the use of this code. However, we encourage you to run and test it for further improvements. 

The current open version is made of several Drupal 8 modules:

- addresses book
- products and services
- assets
- documents management
- sales (purchases, quotations, invoices)
- human resources
- logistics (delivery orders, stock list)
- project management
- report drafting
- finance (accounts, reporting, journal) 

An online demo site is available [here](https://ek.demo.arrea-systems.com/ek-demo-management-software) (with further modules and functions not included into this open source version).

## Install

To install via composer, run:
```
composer require arreasystem/ek:"dev-8.x-dev"
```

Add extra composer.json file to main Drupal composer.json:

```
"merge-plugin": {
            "include": [
                "modules/ek/ek_admin/composer.json",
                ...
```

## Further setup

After modules are enabled, you will need to run the application setup process.
For this, navigate to /ek_admin from your browser. Specific tables and settings will be done after
creating a dedicated database. 
Other settings are not covered here as it implies many custom business questions.
An initial setup description can be view [here](https://arrea-systems.com/tutorial-setup) with other online description manuals.
There are also some videos covering this subject in the tutorial pages.

Some issues are reported and answered on [Drupal project page](https://www.drupal.org/project/issues/2887559?text=&status=All&priorities=All&categories=All&version=All&component=All)

## Contribution

Currently there is no contributor except initial developer.
Any cooperation is welcomed.
The main target (or issue, depending on point of view) would be to make the code fully compatible with Drupal 8 standard
and more specifically create full content entities per modules. This will help extend application,
enhance customization capabilities and interface with other systems.







> Drupal is a registered trademark of Dries Buytaert.



