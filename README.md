# Exchange Rates WordPress Plugin

A WordPress plugin that displays exchange rates with currency calculator.

## Features

- Live exchange rates
- Configurable markup percentage on rates
- Modern, responsive design
- Currency converter with all available currencies
- Three shortcodes for flexible display options
- Automatic rate updates via WordPress cron
- Caching system to prevent excessive requests
- Mobile-friendly interface

## Installation

1. Download the plugin files
2. Upload the plugin folder to the `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure the plugin settings under 'Exchange Rates' in the admin menu

## Usage

The plugin provides three shortcodes:

1. Display exchange rates table:
```
[bber_rates]
```

2. Display currency converter:
```
[bber_calculator]
```

3. Display both rates table and calculator:
```
[bber_combined]
```

### Shortcode Attributes

#### [bber_rates]
- `title` - Custom title for the rates table (default: "Bangkok Bank Exchange Rates")
- `show_last_updated` - Show/hide last updated timestamp (default: "yes")

Example:
```
[bber_rates title="Current Exchange Rates" show_last_updated="no"]
```

#### [bber_calculator]
- `title` - Custom title for the calculator (default: "Currency Converter")

Example:
```
[bber_calculator title="Convert Currencies"]
```

#### [bber_combined]
- `rates_title` - Custom title for the rates table
- `calculator_title` - Custom title for the calculator
- `show_last_updated` - Show/hide last updated timestamp

Example:
```
[bber_combined rates_title="Exchange Rates" calculator_title="Currency Calculator" show_last_updated="yes"]
```

## Configuration

1. Go to 'Exchange Rates' in the WordPress admin menu
2. Set your desired markup percentage (default: 3%)
3. Select which currencies to display in the rates table
4. Configure cache duration (minimum 5 minutes, maximum 24 hours)

## Features

### Exchange Rates Table
- Displays buying and selling rates for selected currencies
- Automatically updates rates via WordPress cron
- Responsive design adapts to screen size
- Shows last updated timestamp
- Includes currency codes and names

### Currency Calculator
- Convert between any available currencies
- Real-time calculations using latest rates
- Swap currencies with one click
- Clear display of conversion results
- Shows exact exchange rate used

### Admin Interface
- Easy-to-use settings page
- Configure markup percentage
- Select displayed currencies
- Manual rate refresh option
- View current rates status

## Technical Details

- Uses WordPress transients for caching
- Implements WordPress coding standards
- Mobile-first responsive design
- AJAX-powered currency conversion
- Efficient rate parsing and storage

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- Allow outbound HTTPS connections to Bangkok Bank website

## Support

For support or feature requests, please create an issue in the plugin's repository.

## License

This plugin is licensed under the GPL v2 or later.


