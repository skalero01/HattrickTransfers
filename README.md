# HattrickTransfer #

Laravel project that allows you to programm transfers for your hattrick.org team.
This project execute the google chrome browser and make the search automatically, for you avoid looking in the whole hattrick site.

**Features**
- You can search depending of the skills without limit
- You can search in any ages without limit
- You can search depending of the best position of the player
- You can filter depending of the HTMS potential and actual value
- You can filter depending of the maximum stars generated on a match (Setting the maximum form and stamina)

Installation
------------
- Clone the project
- Execute `composer update` in the folder
- Create a `.env` file and add this data:
```
HATTRICK_TEAM='Your teams name'
HATTRICK_USER=yourUser
HATTRICK_PASS=yourPass
```
- Install chromedriver and execute it
- Thats all!

Transfers use
------
- Execute `php artisan hattrick:transfers` command
- You can modify the file in `app\Console\Commands\HattrickTransfers.php` with the desired search
- You can use the next variables for searching: `prices, tsi, stars, potential, position`

Players use
----------
You can import a csv file for hattrick organizer software on `database/csv/playerexport.csv` and execute `php artisan hattrick:players` and you will get all the information of your players.

Problems known
------------
You may have problems with the system because it was made for be working with hattrick in spanish, so, if you have problems change your hattrick setting language to spanish and it should work fine

Help to improve the code
------------
If you want you can help to improve the code making push request. Any question please contact to carlosescobar@weblabor.mx