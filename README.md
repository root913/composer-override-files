# Composer - Override Files Plugin

Using this plugin You can override any vendor class file. This is last resort thing, use it at your own risk.

## Installation

```bash
composer require root913/composer-override-files
```

## Usage

Just add those line to composer.json.

Path specified in "path" option should relative to directory with composer.json.

Now You can override files by placing them in specified directory. 

Files directory structure should be same as in vendor.

example composer.json
```json
{
    "extra": {
        "override_files": {
            "path": "overrides"
        }
    }
}
```

## Options
| Name                  | Description                                                               |
| --------------------- | ------------------------------------------------------------------------- |
| path                  | Path to directory with override files                                     |
| base_vendor_dir       | Override root vendor directory where plugin will search for files         |
| generate_origin_file  | If true will generate original file with "Origin" suffix for inheritance  |

## Debugging

To debug output autoload object execute:
```bash
composer dumpautoload -vvv
```

## [Examples](examples)
