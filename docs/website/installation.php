<?php
 $title = "Installation";
 include("inc/page-header.php");
?>
<h1>Before Starting</h1>

<h2>Debian Users</h2>
<p>Ideally you will be running a recent Debian (or Ubuntu) release and will
be able to add:</p>
<pre>
deb http://debian.mcmillan.net.nz/debian lenny awm
</pre>
<p>to your <code>/etc/apt/sources.list</code>.  Once you have done that you
can use <code>apt-get</code> or <code>synaptic</code> or some other equivalent package
manager to fetch and install <code>DAViCal</code> and all the dependencies.</p>

<p>This repository is signed by Andrew McMillan's public key, which you can install so that
you don't get asked for confirmation all the time:</p>

<pre>
sudo apt-key advanced --keyserver subkeys.pgp.net --recv-keys CCA377BD77494424B0DB674F8C90347F8F068012
</pre>

<p>Skip to the "Database Setup" part if you have done that already.</p>


<h2>Other Linux Users</h2>
<p>Please write something up about your experiences in the Wiki, adding distribution specific
notes to pages somewhere under here:
 <a href="http://wiki.davical.org/w/Installation_Stuff">http://wiki.davical.org/w/Installation_Stuff</a></p>

<h3>RPM Packages of DAViCal</h3>
<p>We have created RPM packages of DAViCal and libawl-php from the .deb packages
using "alien". These are reported to work fine, so use them and then proceed to the
Pre-requisites section below.  If you would like to work with us to create native
RPM packages please get in touch!</p>

<h3>SuSE Linux</h3>
<p>On SuSE Linux you may need to look in /var/lib/pgsql/data/ for the pg_hba.conf file.</p>

<h3>Gentoo</h3>
<p>Davical and the awl library ebuilds are available on the <a href="http://www.gentoo.org/proj/en/sunrise/">sunrise overlay</a>.
You'll have to add this overlay to your system:</p>
<pre>
emerge layman
layman -f -a sunrise
echo "source /usr/portage/local/layman/make.conf" >> /etc/make.conf
</pre>

<p>From there, you can keep the overlay in sync with the command:</p>
<pre>layman -s sunrise</pre>

<p>Davical can now be installed with a normal:</p>
<pre>emerge davical</pre>

<h3>Slackware, BSD and the rest</h3>

<p>You will need to download the latest versions of the <code>DAViCal</code> and <code>awl</code> packages
from the <a href="http://sourceforge.net/project/showfiles.php?group_id=179845">sourceforge download page for DAViCal</a>.</p>
<p>You will need to untar these.  Preferably you will untar them from within the "<code>/usr/share</code>" directory and everything
will be in it's expected location (well, except the docs, but it will at least be tidy and everything will be in one place).</p>

<p>We would like to hear from non-Debian users regarding things which might have been missed, or things you have
learned about the system, so please write about your installation experiences on the Wiki, or post a message
in the Sourceforge forums.</p>

<h2>Non-Linux Users</h2>
<p>We would really like to hear from you.  As far as we can see there is no reason why this
can't all work on FreeBSD, Microsoft Windows, VMS, Mac OS or whatever else, as long as the
pre-requisites are able to be installed.</p>
<p>For Unix and unix-like operating systems the "Other Linux Users" instructions are likely
to be reasonably close to what you need.  For other systems everything will need some
adjustment, and in particular some of the path name and shell expectations coded into
the database creation scripts are likely to need love.</p>
<p>We're available to answer questions, anyway :-)</p>

<h1>Pre-requisites</h1>

<p>DAViCal depends on a number of things.  Firstly, it depends
on Andrew's Web Libraries (AWL) which is a set of useful
PHP functions and objects written by Andrew McMillan over
a number of years.</p>

<p>The following other software is also needed:</p>
<ul>
  <li>Apache: 1.3.x or 2.x.x</li>
  <li>PHP: 5.0 or greater</li>
  <li>PostgreSQL: 8.1 or greater</li>
</ul>

<p>The PostgreSQL database may be installed on a server other
than the web server, and that kind of situation is recommended
if you want to increase the security or scalability of your
installation.</p>

<p>Since the CalDAV store takes over a significant amount of path
hierarchy, it can be easier in it's own virtual
host.  If you want it to operate within the web root of some
other application there are instructions on the Wiki about doing this,
as well as other fancy tricks such as configuring URL rewriting in
order to shorten the path.</p>


<h1>Database Setup</h1>

<h2>Creating the Web User for the Database</h2>

<p>On your database server you will need to create a user called
'davical_app' which should not be able to create databases or users,
and which will be granted minimum privileges for the application.</p>
<pre>
createuser --no-createdb --no-createrole davical_app
</pre>

<p>PostgreSQL 7.4 named things slightly differently, so you
should use something like:</p>

<pre>
createuser --no-createdb --no-adduser davical_app
</pre>

<p>You may need to become the 'postgres' user to do this, in which case
you will need to be the postgres user to create the database as well.
For example (for PostgreSQL 8.x):</p>
<pre>
su postgres -c createuser --no-createdb --no-createrole davical_app
</pre>

<h2>Creating and Building the Database</h2>

<p>To create the database itself, run the script:</p>
<pre>
dba/create-database.sh
</pre>
<p>Note that this script calls the AWL database scripts as part
of itself and it expects them to be located in /usr/share/awl/dba
which might be a reasonable place, but it might not be where you
have put them.</p>

<p>Similarly to creating the user, this script also expects to be
running as a user who has rights to create a new database, so you
may need to do this as the "postgres" user, for example:</p>
<pre>
su postgres -c /usr/share/davical/dba/create-database.sh
</pre>

<h2>Connecting to the Database</h2>

<p>Once your database has been created, you may also need to
edit your pg_hba.conf file in order to grant the application
access to the database as the 'davical_app' user.</p>

<p>In a simple installation, where you do not have untrusted
users on your database server, and your database is on the same
computer as the web server, the following line (near the top
of the pg_hba.conf file) should be enough:</p>

<pre>
local   davical    davical_app   trust
</pre>

<p>This means that anyone on the local computer (including the
web application) will have rights to connect to the DAViCal
database as the 'davical_app' user.  It will not allow remote access,
or access as any user other than 'davical_app'.</p>

<p>If you want to connect to the database over TCP/IP rather than
unix sockets, the line in the pg_hba.conf file should look something
like:</p>

<pre>
host davical davical_app 127.0.0.1/32 trust
</pre>

<p>If the webserver is on a different machine to the database, that
IP address will need to change to the webserver's IP address.</p>

<p>If you want greater security, or if you want to have the
database on a different server, you should read the
<a href="http://www.postgresql.org/docs/8.3/interactive/client-authentication.html">PostgreSQL documentation on pg_hba.conf</a>
for the version you are using.</p>

<p>Once you have changed the pg_hba.conf file you will need to
reload or restart the PostgreSQL process for the change to come
into effect.</p>

<h1>Apache Configuration</h1>
<h2>Relative to an existing DocumentRoot</h2>

<p>You can create a symlink from an existing web root directory to the
/usr/share/rscds/htdocs directory, such as:</p>

<pre>
cd /my/apache/docroot
ln -s /usr/share/davical/htdocs davical
</pre>

You will need to change your global PHP configuration to include the
directory '/usr/share/awl/inc' in the 'include_path' setting, along with
any other directories already needed by other applications.

You will also need to ensure that 'magic_quotes_gpc' is off.

<h2>Using a Virtual Host</h2>

<p>Your Apache instance needs to be configured for Virtual Hosts.  If
this is not already the case you may want to read some documentation
about that, and you most likely will want to ensure that any existing
site becomes the **default** virtual host, with DAViCal only being a
single virtual host.</p>

<p>I use a Virtual Host stanza like this:</p>
<pre>
#
# Virtual Host def for Debian packaged DAViCal
&lt;VirtualHost 123.4.56.78 >
  DocumentRoot /usr/share/davical/htdocs
  DirectoryIndex index.php index.html
  ServerName davical.example.net
  ServerAlias calendar.example.net
  Alias /images/ /usr/share/davical/htdocs/images/
  &lt;Directory /usr/share/davical/htdocs/>
      AllowOverride None
      Order allow,deny
      Allow from all
  &lt;/Directory>
  php_value include_path /usr/share/awl/inc
  php_value magic_quotes_gpc 0
  php_value register_globals 0
  php_value open_basedir 1
  php_value error_reporting "E_ALL & ~E_NOTICE"
  php_value default_charset "utf-8"
&lt;/VirtualHost>
</pre>

<p>Replace 123.4.56.78 with your own IP address, of course (you can
use a name, but your webserver may fail on restart if DNS happens
to be borked at that time).</p>

<p>The various paths and names need to be changed to reflect your
own installation, although those are the recommended locations
for the various pieces of the code (and are standard if you
installed from a package.</p>

<p>Once your VHost is installed an working correctly, you should be
able to browse to that address and see a page telling you that
you need to configure DAViCal.</p>

<p>On Debian systems (or derivatives such as Ubuntu), when you are
using Apache 2, you should put this definition in the /etc/apache2/sites-available
directory and you can use the 'a2ensite' command to enable it.</p>


<h1>DAViCal Configuration</h1>

<p>The DAViCal configuration generally resides in /etc/davical/&lt;domain&gt;-conf.php
and is a regular PHP file which sets (or overrides) some specific variables.</p>

<pre>
&lt;?php
//  $c->domain_name = "calendar.example.net";
//  $c->sysabbr     = 'DAViCal';
//  $c->admin_email = 'admin@example.net';
//  $c->system_name = "Example DAViCal Server";
//  $c->enable_row_linking = true;

  $c->pg_connect[] = 'dbname=davical port=5432 user=davical_app';

</pre>

<p>See the wiki for the full list of <a href="http://wiki.davical.org/w/Configuration_settings">DAViCal configuration settings</a>.</p>

<p>Multiple values may be specified for the PostgreSQL connect string,
so that you can (e.g.) use PGPool to cache the database connection
but fall back to a raw database connection if it is not running.</p>

<p>You should set the 'domain_name' and 'admin_email' as they are used
within the system for constructing URLs, and for notifying some
kinds of events.</p>

<p>If you are in a non-English locale, you can set the default_locale
configuration to one of the supported locales.</p>

<h1>Supported Locales</h1>
<p>At present the following locales are supported:</p>
<ul>
<li>English</li>
<li>German / Deutsch</li>
<li>Spanish / Español</li>
<li>French / Français</li>
<li>Russian / Русский</li>
<li>Netherlands / Nederlands</li>
<li>Polish / Polski</li>
<li>Hungarian / Magyar</li>
<li>Japanese / 日本語</li>
</ul>

<p>If you want locale support you probably know more about configuring it than me, but
at this stage it should be noted that all translations are UTF-8, and pages are
served as UTF-8, so you will need to ensure that the UTF-8 versions of these locales
are supported on your system.</p>


<h1>Completed?</h1>

<p>If all is going well you should now be able to browse to the admin
pages and log in as 'admin' (the password is the bit after the '**'
in the 'password' field of the 'usr' table so:</p>
<pre>
psql davical -c 'select username, password from usr;'
</pre>

<p>should show you a list.  Note that once you change a password it
won't be readable in this way - only the initial configuration
leaves passwords readable like this for security reasons.</p>

<p>If all is working then you should be ready to configure a client
to use this, and the docs for that are elsewhere.</p>

<p>If you had to do something else that is not covered here, or if you have any other notes
you want to add to help others through the installation process, please write something up
about your experiences in the Wiki, including distribution specific notes, to pages somewhere under here:
 <a href="http://wiki.davical.org/w/Installation_Stuff">http://wiki.davical.org/w/Installation_Stuff</a></p>


<h1>Upgrades</h1>

<p>Whenever you upgrade the DAViCal application to a new version you will need to
run dba/update-davical-database which will apply any pending database patches, as well as
enabling any new translations.</p>

<p>In due course the running of this script will be able to be handled by
the packaging system, but that change will not happen until early 2007.</p>

<?php
 include("inc/page-footer.php");
