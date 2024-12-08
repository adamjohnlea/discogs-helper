# Discogs Helper

A PHP application to manage your music collection using the Discogs API. Search, preview, and save releases with cover art and detailed information.

## My Collection Screen

<p align="center">
  <img src="docs/images/discogs-helper-example-image.png" alt="Discogs Helper Main Screen Screenshot" width="1023">
</p>

## Album Details

<p align="center">
  <img src="docs/images/discogs-helper-release-detail.png" alt="Discogs Helper Album Detail Screenshot" width="1023">
</p>

## Features

- Search releases by artist/title or UPC/barcode
- Preview release details before adding to (local) collection - currently not added to discogs
- Select from available cover images
- Import existing Discogs collection
- View detailed release information
- SQLite database for simple deployment
- Cover image storage and management

## Requirements

- PHP 8.3 or higher
- SQLite 3
- Composer
- Discogs API credentials

## Installation

1. Clone the repository:
   git clone https://github.com/yourusername/discogs-helper.git then: `cd discogs-helper`

2. Install dependencies:
   `composer install`

3. Create environment file:
   `cp .env.example .env`

4. Configure your Discogs API credentials in .env:<br>
   `DISCOGS_CONSUMER_KEY=your_key_here`<br>
   `DISCOGS_CONSUMER_SECRET=your_secret_here`

5. Set up directory permissions:<br>
  `chmod -R 755 database`<br>
  `chmod -R 755 public/images/covers`<br>
  `chmod -R 755 logs`

6. Run database migrations:<br>
   `php bin/migrate.php`
## Usage

1. Start the development server:
   `php -S localhost:8000 -t public`

2. Open in your browser:
   http://localhost:8000

3. Features:
   - Click "Add New Release" to search for releases
   - Enter an artist/title or UPC/barcode
   - Click on a search result to view details
   - Select your preferred cover image
   - Click "Add to Collection" to save the release
   - View your collection on the main page
   - Click on any release to view its details
   - Use "Import Collection" to import your existing Discogs collection

## Import Feature

The collection import:
- Processes releases in batches to handle rate limits
- Automatically downloads cover images
- Preserves original Discogs date added
- Skips duplicates automatically
- Shows progress with a visual indicator   

## Database

The application uses SQLite for simplicity. The database file is automatically created at 
database/discogs.sqlite when you first run the application.

## Configuration

The following environment variables are required:
- `DISCOGS_CONSUMER_KEY: Your Discogs API consumer key`
- `DISCOGS_CONSUMER_SECRET: Your Discogs API consumer secret`

Optional configuration can be modified in config/config.php:
- Database location
- User agent string
- API rate limiting parameters

## Security

- All user inputs are sanitized
- Cover images are stored with randomized filenames
- API credentials are stored in environment variables
- Database file is outside web root

## Error Handling

- Logs are stored in the logs/ directory
- API rate limiting is handled automatically
- Duplicate releases are prevented
- Failed operations are logged with details

## Development

- The application uses a simple PHP template system
- CSS is included inline for simplicity
- Error logging is done to the logs/ directory
- Rate limiting automatically handles Discogs API restrictions

## Contributing

1. Fork the repository
2. Create your feature branch (git checkout -b feature/amazing-feature)
3. Commit your changes (git commit -m 'Add some amazing feature')
4. Push to the branch (git push origin feature/amazing-feature)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Acknowledgments

- [Discogs API](https://www.discogs.com/developers) for providing the music database
- [SQLite](https://www.sqlite.org/) for reliable database storage
- [GuzzleHTTP](https://docs.guzzlephp.org/) for HTTP client functionality
- [phpdotenv](https://github.com/vlucas/phpdotenv) for environment configuration

## Support

If you encounter any problems or have suggestions, please open an issue in the GitHub repository. 