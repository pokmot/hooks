# Wordpress Hooks Reworked

This code contains improvements on the Wordpress hooks system.

It works in a very similar way to the original Wordpress code, but has no dependencies at all.

Rather than functions, it's a namespaced static class using PSR-4.

Load like normal composer packages:

```
use pokmot\Hooks\Hooks as Hooks;
include 'vendor/autoload.php';
```

Add a filter:

`Hooks::addFilter('my_filter_name', 'filter_function');`
`Hooks::addFilter('my_filter_name', '\My\Namespaced\Class::static_filter_method');`
`Hooks::addFilter('my_filter_name', [$this, 'static_filter_method']);`

The above filters are all added with standard priority and will be executed in order.

Call a filter:

`$value = Hooks::filter('my_filter_name', 123);`

You can add an event instead.

`Hooks::addEvent('my_filter_name', 'filter_function');`

Trigger an event:

`Hooks::trigger('my_filter_name');`

You can pass any number of arguments; only the first argument will be changed by the function (UNLESS they are objects - no cloning involved):

`$value = Hooks::filter('my_filter_name', 123, 456, 789);`

Very important - both for filters and triggers, none of the arguments are immutable IF they are objects. However, already immutable objects will of course stay immutable.

## Use case

Use _whenever_ you want to be able to change a value, or signal that something has happened in your code.

Perfect places to use it:

- When processing user input:
  - `list($get, $post) = Hooks::filter('filter_incoming_params', [$_GET, $_POST]);`
- Upon login:
  - `$user_can_login = Hooks::filter('check_user_blacklist', true, $user);`
- When adding a new record in your database (send welcome email, post in a Slack channel etc):
  - `Hooks::trigger('new_user_record', $user);`

Writing your own framework? Some places you can callfilters or triggers it are:

- pre.run - very first call before anything is processed at all (apart from autoload)
- pre.boot - before setting up your framework
- post.boot - after setting up your framework
- routes - pass through routes or the routing object so you can add modules
- pre.config - add more config files for instance for local configuration
- post.config - change configuration, for instance for local configuration
- pre.middleware - you have ability to add more middleware
- post.middleware - or change the behaviour after middleware has run
- pre.controller - set up controller specific behaviour
- post.controller - or ensure resources are released
- response - amend the response if needed, for instance adding headers
- output - change the output itself
- post.run - final bits e.g. close the connection, avoid aborts and send emails without letting the client wait

## Why has this class been created?

Quite simply, the Wordpress hook system is powerful and easy to use. It's a key part of why Wordpress is successful as it allows anyone to change the behaviour of Wordpress on a very deep level, without actually hacking the core code.

I wanted this for my own systems. At the time there weren't a huge number of options. Now there are more, both Laravel and Symfony as well as The League of Extraordinary Packages offer similar functionality, but I found all of them either cumbersome or requiring lots of extra components I didn't really want to install.

For instance, in Symfony you have to create a listener and add to a dispatcher, and you have Subscribers etc.

Here, you simply call the filter whenever it's needed in your code. Any piece of code can attach itself and listen with a one-line static class call.

Easy.

I realise making it a static class means it's difficult to mock in unit tests. If that's a concern then this class might not be right for you. Consider making it a non-static class; it should be easy.

However, a simple call to `Hooks::clear();` is enough to clear out all filters and events - I would suggest you should create your unit tests with NO filters or events, and test those separately, so this should not really be an issue. Just issue clear() in your setup or tear-down of the unit tests.

## Next Steps

I have done some refactoring of the code before publishing it on GitHub; I have used this code in production for several years but I am keeping the revision to 0.x until I have fully tested the refactoring.

The function calls will not change between 0.x and 1.x versions.

SemVer will be followed from v 1.x onwards.

## What's left to do?

- Unit Tests
- Better examples
- Better documentation (this file)

## Licensing

I am guessing this should be under the GPLv2 license as the code is derived from the Wordpress Hooks system which is GPLv2. Personally I'd like to make this a MIT license - if anyone has feedback on this, feel free to let me know by creating an Issue.

How that affects your code I leave as an exercise to the reader; any feedback is appreciated.