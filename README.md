TMPL - sounds like *"simple"*
=============================

Simple templating lib for PHP
-----------------------------

Tmpl (speak *"Timpl"*) is an easy (and currently very limited) templating library. It is designed to be very lightweight and easy to install (what basicallly means that it **doesn't have to be installed**).

Features:
---------

### only one file to include:
```php
require('tmpl.php')
```

### easy usage:
```php
$tmpl = new Tmpl('filename.tmpl');
$output = $tmpl->render(array(
    'variableName'  => 'value',
    'variableName2' => 'value2'
));
```
  
### syntactically similar to Twig and Tornado Template:
- ``{{ x }}`` outputs the string representation of the
  variable x
- ``{{ x.y }}`` outputs the string representation of the
  property y of the object x or index y of x
  (depending on whether x is an object or an array)
- conditional output via
    - ``{% if condition %}``
    - ``{% elif condition %}``
    - ``{% else %}``
    - ``{% end %}``
- iterate over collections
    - ``{% for i in collection %}``
    - ``{% end %}``
-  basic arithmetical and logical operators
    - unary operators:
        - ``not x``
        - ``- x``
    - binary operators
        - ``x + y``
        - ``x - y``
        - ``x * y``
        - ``x / y``
        - ``x % y``
        - ``x == y``
        - ``x != y``
        - ``x >= y``
        - ``x <= y``
        - ``x > y``
        - ``x < y``
        - ``x and y``
        - ``x or y``
        - ``x in y``
- comments via ``{# comment string #}``

Installation
------------
No installation required, just copy the file "tmpl.php"
into your project and include it in your source code via
``require_once('tmpl.php');``.

Usage
-----
To render a template, there's only two steps:

- loading the template file
- rendering the template into a string

```php
// load the template file
$tmpl = new Tmpl('filename.tmpl');

// render the template using your variables
$output = $tmpl->render(array(
    'variableName'  => 'value',
    'variableName2' => 'value2',
    'myCollection'  => array('x' => 42,
                             'y' => 7,
                             'z' => 'something')
));

```