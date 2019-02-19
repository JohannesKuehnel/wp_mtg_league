# MTG League Tracker
MTG League Tracker is a Wordpress Plugin to display standings for trading card game [Magic: The Gathering](https://magic.wizards.com/) leagues and tournaments. Initially developed for the [Austrian Magic Championship](https://www.austrianmagic.at/), MTG League Tracker could be useful for similar projects.
The plugin relies on [The Events Calendar](https://wordpress.org/plugins/the-events-calendar/) to handle events. Tournament result files (XML) from [Wizard Event Reporter](https://wpn.wizards.com/wer) (WER) can then be uploaded. Standings are automatically updated and displayable via shortcodes.

## Project Status
The plugin is marked as **EXPERIMENTAL**, as many options are still hard-coded and the data handling might be volatile, due to features still missing.

## Local Development
If you want to test the plugin locally, just use the provided Docker environment.

```
docker-compose up -d
```

The site is reachable under `http://localhost:8080/` shortly after.

Don't forget to install [The Events Calendar](https://wordpress.org/plugins/the-events-calendar/) plugin once your site is up and running before activating MTG League Tracker.

If your Wordpress installation in Docker doesn't let you install plugins, make sure to set the correct permissions.
```
docker-compose exec -T wordpress sh
chown -cR www-data:www-data /var/www/html/wp-content
```

## Authors
* **Johannes Kühnel** - https://github.com/JohannesKuehnel

## License
This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details
