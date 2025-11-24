# PREVALIDATION-BUG.md

As I'm not very comfortable with all those Pul Requests things, I put here a explanation about my pull request.
Feel free to remove this file if you plan to merge this request!

## Bug found in version 1.0.9

I downloaded the 1.0.9 version of this extension on the GitHub, and whenever I called the URL, I got an 'empty 200' answer.

Each sollicitation add a line in the `{iTop root directory}/log/error.log`:

~~~text
2025-11-24 11:38:05 | Error   |       | Metric description has no description. Please provide it. | IssueLog |||
~~~

The message itself was nearly self explanatory 'description has no description'. I did suspect a routine going a little too deep…

Long story short, I find out that the culprit was the function `ReadMetrics` in the library `src/Controller/Controller.php`.

The fix was indeed an easy one, but needed a change in the function call. This function was (to my knowledge) called only once, and this call was in the same file, so I had also to modify the call.

## Today tests

As Olivier send me a link to the GitHub repo, today I did sone more tests.

### Test 1.0.9 again on a fresh installation

I did a fresh install of iTop 3.2.2-1 with the deme dataset, an only one extension : combodo-monitoring 1.0.9.

Sure enough, the bug was still present.

Then I removed this version, and installed the content of the master branch.

The bug was still present.

As I noticed no change in the src/Controller/Controller.php file between 1.0.9 and the current state of the master branch, I simply put my version of the file in the contrib, and everything works fine.

### Test mode

I didn't use any Prometheus or complicated tool to test the extension, only Curl.

Here are the tests results.

#### Unpatched contrib

~~~bash
time curl -i 'https://kub05.sacem.fr/zitop/pages/exec.php?access_token=gabuzomeu123&exec_env=production&exec_module=combodo-monitoring&exec_page=index.php&collection=itop_active_sessions'
HTTP/1.1 200 OK
Date: Mon, 24 Nov 2025 10:53:14 GMT
Server: Apache/2.4.58 (Ubuntu)
Set-Cookie: itop-fb1e04ee216011ed4efef0dc35cc125a=l3gi8omj9d8n131r085u17es00; path=/
Expires: Thu, 19 Nov 1981 08:52:00 GMT
Cache-Control: no-store, no-cache, must-revalidate
Pragma: no-cache
Set-Cookie: itop-fb1e04ee216011ed4efef0dc35cc125a=l3gi8omj9d8n131r085u17es00; path=/
Content-Length: 0
Content-Type: text/plain; charset=UTF-8


real    0m0.186s
user    0m0.107s
sys     0m0.029s
~~~

And one mor line in `log/error.log`

#### Patched contrib

~~~bash
time curl -i 'https://kub05.sacem.fr/zitop/pages/exec.php?access_token=gabuzomeu123&exec_env=production&exec_module=combodo-monitoring&exec_page=index.php&collection=itop_active_sessions'
HTTP/1.1 200 OK
Date: Mon, 24 Nov 2025 10:58:39 GMT
Server: Apache/2.4.58 (Ubuntu)
Set-Cookie: itop-fb1e04ee216011ed4efef0dc35cc125a=6sitianrf2jnodt3jnvn52eqst; path=/
Expires: Thu, 19 Nov 1981 08:52:00 GMT
Cache-Control: no-store, no-cache, must-revalidate
Pragma: no-cache
Set-Cookie: itop-fb1e04ee216011ed4efef0dc35cc125a=6sitianrf2jnodt3jnvn52eqst; path=/
Vary: Accept-Encoding
Content-Length: 418
Content-Type: text/plain; charset=UTF-8

# Active session count
itop_active_sessions_count{login_mode="no_auth",context=""} 4

# Active session count
itop_active_sessions_count{login_mode="form",context=""} 1

# Sum of active session elapsed time in seconds
itop_active_sessions_elapsedinsecond_sum{login_mode="form",context=""} 0

# Max elapsed time in seconds amoung active sessions
itop_active_sessions_elapsedinsecond_max{login_mode="form",context=""} 0


real    0m0.211s
user    0m0.137s
sys     0m0.023s
~~~

Thanks,

Pascal
schirrms@schirrms.net