# Composer - Override Files Plugin

Using this plugin You can override any vendor file.

## Installation

```bash
composer require root913/composer-override-files
```

## Usage

Just add those line to composer.json.

Path specified in "path" option should be in same directory as composer.json.

Now You can override files by placing them in specified directory. 

Files directory structure should be same as in vendor.

```json
// composer.json (project)
{
    "extra": {
        "override_files": {
            "path": "overrides"
        }
    }
}
```