# HattrickTransfer #

Laravel project that allows you to programm transfers for your hattrick.org team.
This project execute the google chrome browser and make the search automatically, for you avoid looking in the whole hattrick site.

**Features**
- You can search depending of the skills without limit
- You can search in any ages without limit
- You can search depending of the best position of the player
- You can filter depending of the HTMS potential and actual value
- You can filter depending of the maximum stars generated on a match (Setting the maximum form and stamina)

**How it works**
- You put the custom search you want to do
- The system with login automatically to your account and will make the search one by one
- Once it search everything it will order by time and 5 minutes before the limit finish will make a offer and will keep checking if you are still the winner.
- If the price is more that was configurated it will stop and continue with the next player in the list.

**Things to consider**
- You need to be checking your buyings periodically because there is probability to buy a lot of players (More that what you needed)
- Its important to configurate the name of your team correcly because with the name will know if you are winning the offer.

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
Or if you are a develop you can help to fix this changes.

Help to improve the code
------------
If you want you can help to improve the code making push request. Any question please contact to carlosescobar@weblabor.mx