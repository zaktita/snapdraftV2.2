# SnapDraft - AI Visual Generator Instructions

## Project Overview
SnapDraft generates brand-consistent visual content from CSV data by analyzing reference images to understand brand style and applying that consistency across batch-generated visuals.

## Core Workflow

### 1. Brand Analysis Phase
- Users upload 5-10 reference images that represent their brand style
- AI analyzes these images to extract brand guidelines (colors, typography, composition patterns)
- Creates an internal style guide for consistent generation

### 2. Content Input Phase
- Users provide content data via CSV with columns: `title`, `description`, `format`
- Each row represents one visual to be generated
- Optional: Users can upload product images to be featured in generated visuals

### 3. Generation Phase
- AI processes each CSV row individually
- Generates visuals that match the extracted brand style
- Maintains consistency across all generated images
- Applies product images when provided

## Application Structure

### Main User Interfaces
- **Projects Dashboard**  - Central hub showing all user projects
- **Canvas Editor**  - Visual editing workspace for fine-tuning
- **Settings** - settings and user preferences

### Wizard Workflows
- **CSV Wizard**  - 3-step process for CSV-based generation
- **Images Wizard**  - Image-focused project creation
- **Text Wizard**  - Text-based content generation

### Navigation Pattern
- Unified sidebar across all pages (except canvas editor)
- Project switcher with dropdown
- Quick actions: New Project, Search (Cmd/Ctrl+K)
- Status indicators: Updates, Credits, Notifications

## Key Data Flows

### Upload Flow
1. User selects reference images (drag & drop or file picker)
2. User uploads CSV file with content data
3. System validates file formats and content structure
4. Files stored temporarily for processing

### Generation Flow
1. Reference images analyzed for brand characteristics
2. CSV parsed to extract individual content items
3. For each CSV row:
   - AI creates contextual prompt combining brand style + content
   - Visual generated maintaining brand consistency
   - Result stored with metadata
4. Batch results compiled and presented to user

### Edit Flow
1. User selects generated image for editing
2. Canvas editor loads with image and editing tools
3. User can apply masks, filters, or regenerate portions
4. Updated image saved as new version

## File Organization Patterns

### Input Requirements
- **Reference Images**: 5-10 brand representative images (JPG, PNG, WebP)
- **CSV Format**: Must include `title`, `description`, `format` columns
- **Product Images**: Optional overlay images (up to 5)

### Output Structure
- Individual PNG files for each generated visual
- Organized naming: `{index}_{title}_{format}.png`
- Generation metadata and reports
- Style guide documentation

## User Experience Patterns

### Project Creation
1. Choose wizard type (CSV, Images, or Text)
2. Upload required files
3. Configure generation settings
4. Review and start generation
5. Monitor progress and results

### Batch Operations
- Generate multiple visuals simultaneously
- Track generation progress with real-time updates
- Handle errors gracefully with detailed feedback
- Allow partial batch completion

### Quality Control
- Preview generated images before final download
- Edit individual images as needed
- Regenerate specific items without affecting others
- Maintain version history

## Integration Points

### AI Services
- Primary: Google Gemini for analysis and generation
- Secondary: OpenRouter for model testing and alternatives
- Rate limiting and error handling built-in

### File Management
- Temporary upload storage
- Automatic cleanup of processed files
- Support for multiple file formats
- Size and quantity limitations

## Error Handling Patterns
- Graceful fallbacks when AI services fail
- Detailed error messages with actionable suggestions
- Partial batch completion when some items fail
- Comprehensive logging for troubleshooting

## Performance Considerations
- 2-second delays between AI generations to respect rate limits
- Progress indicators for long-running batch operations
- Chunked processing for large CSV files
- Efficient file upload handling with size validation

