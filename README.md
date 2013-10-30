Wordpress Required Plugins
==========================

Declares required plugins and prompts for installation if not present

## Usage

```php
<?php
add_action('required_plugins_register', function() {
    required_plugins_register(array(
        array(
            'name'                  => 'Plugin Name', // The plugin name
            'slug'                  => 'plugin-name', // The plugin slug (typically the folder name)
            'required'              => true, // If false, the plugin is only 'recommended' instead of required
            'force_activation'      => true, // If true, plugin is activated upon theme activation and cannot be deactivated until theme switch
        ),
    ));
});
?>
```

## Author

 * [Sébastien Lavoie <seb@wemakecustom.com>](http://www.wemakecustom.com)

The original plugin is located at https://github.com/thomasgriffin/TGM-Plugin-Activation

## Rewriting

The plugin was rewritten for better code management/styling and to better integrate with composer.

The usage of Composer and namespaces is not The Wordpress Way so this was never meant to be merged into the official plugin.


