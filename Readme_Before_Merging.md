## Contribution Work Flow

```
Date: 21 July 2023

Contribution by -
R Cube Dev
https://rcubedev.in/


Message to Developer -
If everything seems to be fine, feel free to delete this file before merging. This file is just to inform admin regarding the changes made to this project.

```

### Issue: Multiple Visit Logs ( Middleware )

![x72F8.png](https://s6.imgcdn.dev/x72F8.png)

Middleware Log Visits automatically, but does not prevent from duplicate or multiple visits entries into database.

When user refreshes the &nbsp; `vistor()->visit()` &nbsp; is fired everytime, resulting in increase in database request and log every second.

#### Solution:

When user hits the route protected with &nbsp; `LogVisits` &nbsp; middleware, it will first check in &nbsp; `Session`&nbsp; for 'visit' key which contains &nbsp; `current $request IP and URL.` &nbsp;

![x7xxy.png](https://s6.imgcdn.dev/x7xxy.png)

If key doest not exist, it will store the current request IP and URL into visit key array, then fires the &nbsp; `visitor()->visit()` &nbsp; which will log the record into database.

If Key Exists, then it will check the previously stored IP and URL are same or not, if both are same, then visit() method won't be fired. If any of them is different, &nbsp; `visitor()->visit()` &nbsp; will be fired, and session data will be updated with new values.

![x7OC2.png](https://s6.imgcdn.dev/x7OC2.png)

![x7Z7i.png](https://s6.imgcdn.dev/x7Z7i.png)

![x793H.png](https://s6.imgcdn.dev/x793H.png)

![x7VwS.png](https://s6.imgcdn.dev/x7VwS.png)

![x7fUC.png](https://s6.imgcdn.dev/x7fUC.png)

**Note:** Only Middleware is protected with session, the `visitor()->visit()` is not, if user call this method directly from controller, then duplicates will be created on every request.;

Update the readme file accordingly encouraging to use Middleware directly to route instead of calling `visitor()->visit()`, or modify `visit()` method same as Middleware.

---

### Feature: Add Country Field to Database

User asked for a Country, Country Code, Latitude and Logitude feature

#### Solution:

First we need to an package to the composer which is required to fetch the country and other information from IP Address, the best open source and well documented package is &nbsp; `PulkitJalan\GeoIP`

[Check out the Package Here](https://github.com/pulkitjalan/geoip)

In `visitor.php` added a GeoIP instance on construct method, and public function:

- `country()` : Get the Country by the `IP`, ip's like '127.0.0.1' won't be detected and will return null.
- `countryCode()` : Get the Country Code by the `IP`, ip's like '127.0.0.1' won't be detected and will return null. 

***Note:** this methods are useful when using charts canvas or setting up some dynamic maps. Or showing Pageviews Data in Tables like UX.*

![x7tRe.png](https://s6.imgcdn.dev/x7tRe.png)

### Added Unique Visitors and All Time Page 

- `getUniqueVisitors()` : Returns a number (Int) of Unique Visitors, distinct by IP, for specified days or default `7 Days`. Excluding `Bots and Crawlers`.

```php
// How to Use

visitor()->getUninqueVisitors(); // return unique visitors within 7 Days
visitor()->getUninqueVisitors(4); // return unique visitors within 4 Days
```

- `getAllTimeVists()` : Return a number (Int) of Numbers of Visits. `Bot visits is also included`.

```php
// How to Use;

visitor()->getAllTimeVists(); // Returns all time visits.
visitor()->getAllTimeVists(5); // Returns number of visits within 5 Days
```

![x7Wo0.png](https://s6.imgcdn.dev/x7Wo0.png)

----

## Support

Any questions or support required regarding this pull request, I kindly request you to shoot an email at: rcubedev20@gmail.com or contact me at [@R Cube Dev](https://rcubedev.in/)
