# Dokan Category Attributes Manager

A powerful WordPress plugin for managing category-specific attributes for Dokan vendors with dynamic fields, conditional display, and search filters.

## Features

- **Database-Driven Attribute Sets**: Create unlimited attribute sets with custom fields
- **Category Targeting**: Assign attribute sets to specific store categories
- **Conditional Display**: Fields automatically show/hide based on vendor's selected categories
- **Multiple Field Types**: Select, text, textarea, number, radio, checkbox
- **Three Integration Points**:
  - Vendor Dashboard Settings
  - Public Vendor Store Pages
  - Store Listing Filters
- **Admin UI**: Full WordPress admin interface for managing attributes
- **Import/Export**: JSON-based import/export for portability
- **Responsive Design**: Works on all devices
- **Developer Friendly**: Well-documented, extensible architecture

## Installation

1. Upload the `dokan-category-attributes` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to **Dokan > Category Attributes** to manage attribute sets

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- Dokan Lite or Dokan Pro
- WooCommerce

## Quick Start

### First-Time Setup

After activation, the plugin automatically creates two sample attribute sets:

1. **Physical Attributes** (for Model, Artist, Actor categories)
   - Gender, Age Range, Eye Color, Hair Color, Height, Weight, Body Type, Ethnicity, Skin Tone

2. **Equipment & Skills** (for Cameraman category)
   - Camera Type, Video Resolution, Specialty, Drone Operator, Lighting, Audio, Editing Software, Experience, Live Streaming

### Creating a New Attribute Set

1. Go to **Dokan > Category Attributes**
2. Click **Add New**
3. Configure set details:
   - **Name**: Display name (e.g., "Voice Attributes")
   - **Slug**: Unique identifier (e.g., "voice_attributes")
   - **Icon**: Dashicon name (e.g., "microphone")
   - **Categories**: Select which store categories should see this set
   - **Priority**: Display order (lower numbers first)
   - **Status**: Active or Draft

4. Add fields:
   - Click **Add Field**
   - Enter **Field Label** (e.g., "Voice Type")
   - Enter **Field Name** (e.g., "voice_type")
   - Select **Field Type** (select, text, etc.)
   - Add **Field Options** (for select/radio/checkbox types)
   - Configure visibility checkboxes:
     - **Show in Dashboard**: Vendor can edit this field
     - **Show on Public Store**: Field displays on vendor's public page
     - **Show in Store Filters**: Field available as search filter

5. Drag to reorder fields
6. Click **Publish**

## Usage

### For Vendors

1. Go to **Dashboard > Settings**
2. Select your store categories
3. Category-specific attribute fields appear automatically
4. Fill in your attributes
5. Click **Update Settings**

### For Site Visitors

**Browsing Vendors:**
- Visit the store listing page
- Select a category filter
- Category-specific filters appear
- Filter by attributes to find the perfect vendor

**Viewing Vendor Store:**
- Visit any vendor's store page
- Attribute sections display below store header
- Only filled-in attributes are shown

## Database Structure

### Tables Created

1. **wp_dokan_attribute_sets**
   - Stores attribute set definitions
   - Fields: id, name, slug, icon, categories (JSON), priority, status

2. **wp_dokan_attribute_fields**
   - Stores individual field configurations
   - Fields: id, attribute_set_id, field_name, field_label, field_type, field_options (JSON), required, display_order, show_in_*

3. **wp_dokan_vendor_attributes** (optional)
   - Caches vendor attribute values for faster querying
   - Fields: id, vendor_id, field_id, field_value

Field values are also stored in WordPress `usermeta` table with the field name as the meta key.

## Import/Export

### Exporting an Attribute Set

1. Go to **Dokan > Category Attributes**
2. Scroll to **Import/Export** section
3. Select attribute set from dropdown
4. Click **Export**
5. JSON file downloads automatically

### Importing an Attribute Set

1. Go to **Dokan > Category Attributes**
2. Scroll to **Import/Export** section
3. Click **Choose File** and select JSON file
4. Click **Import**
5. New attribute set created

## Customization

### Styling

Override plugin styles in your theme:

```css
/* Dashboard field labels */
.dca-attribute-section h3 {
    color: your-color;
}

/* Public store section titles */
.vendor-section-title {
    border-bottom-color: your-color;
}

/* Filter section styling */
.filter-group-title {
    background: your-background;
}
```

### Hooks & Filters

Coming soon - developer documentation for extending functionality.

## Troubleshooting

### Fields Not Showing

- Ensure attribute set status is "Active"
- Verify categories are selected in attribute set settings
- Check that "Show in Dashboard/Public/Filters" is enabled for fields
- Ensure vendor has selected a matching category

### Conditional Logic Not Working

- Clear browser cache
- Check browser console for JavaScript errors
- Verify Select2 is loaded (Dokan dependency)

### Import Failed

- Verify JSON format is valid
- Check that file was exported from this plugin
- Ensure field names are unique

## Support

For support, please visit: https://castingagency.co/support

## Changelog

### 1.0.0 (2024)
- Initial release
- Database-driven attribute management
- Admin UI for creating/editing attribute sets
- Conditional field display in dashboard
- Public vendor page display
- Store listing filters
- Import/Export functionality
- Sample data installation

## Credits

Developed by Casting Agency
Built for Dokan multi-vendor marketplace

## License

GPL v2 or later - https://www.gnu.org/licenses/gpl-2.0.html
