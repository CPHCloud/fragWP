fragWP
======

This class provides a framework for fragment caching in Wordpress. It handles both functions and files as the source of the fragments which makes it useful for caching both data and template parts.

#### Usage
Output a fragment with a file as the source
```
$template = 'var/www/themes/mytheme/my_fragment.php';
frag('unique_key', $template);
```

Output a fragment with a function as the source
```
function do_frag(){
  echo 'Fragment content';
}

frag('unique_key', 'do_frag');
```

Output a fragment with a file as the source and set the fragment to expire in an hour
```
function do_frag(){
  echo 'Fragment content';
}
$ttl = 60*60; // One hour in seconds
frag('unique_key', 'do_frag', $ttl));
```

Output a fragment with a file as the source and set the fragment to expire in an hour or if the save_post hook is called
```
function do_frag(){
  echo 'Fragment content';
}
$ttl = 60*60; // One hour in seconds
frag('unique_key', 'do_frag', $ttl, array('save_post)));
```

####Contribute
Pull requests are welcome :)

####Practical
Maintained by [@supertroels](http://www.github.com/supertroels) for [@CPHCloud](http://www.github.com/CPHCloud)

