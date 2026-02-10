# CSV Edit Feature - Implementation Summary

## What Was Added

The CSV Wizard now has three modes for handling CSV data:

### 1. **Upload CSV** (Original)
- Upload a CSV file via drag-and-drop or file picker
- Automatically parses and displays the data

### 2. **Create CSV** (New - Already Implemented)
- Click "Create CSV" tab
- Inline editable table with rows and columns
- Add/remove rows (max 5)
- Add/remove columns (max 6)
- Edit column headers inline
- Delete individual cells
- "Confirm & Continue" button

### 3. **Edit Uploaded CSV** (To Be Implemented)
- After uploading a CSV, an "Edit CSV" button appears
- Click to switch to edit mode
- Same editable interface as "Create CSV"
- Pre-populated with uploaded data
- Save changes or cancel
- Updates the CSV data and regenerates the file

## Implementation Status

✅ **Completed:**
- Create CSV from scratch functionality
- Tab switching between Upload and Create
- Inline table editor with full CRUD
- CSV file generation from editor
- Integration with existing wizard flow

⏳ **To Be Added:**
- Edit mode for uploaded CSVs
- "Edit CSV" button in upload preview
- Toggle between view and edit modes
- Save/Cancel buttons in edit mode

## Technical Implementation

The feature uses React state to manage:
- `uploadMode`: 'upload' | 'create'
- `editableData`: Array of row objects
- `editableHeaders`: Array of column names
- `isEditingCSV`: Boolean for edit mode toggle

Functions added:
- `addRow()`: Add new empty row
- `removeRow(index)`: Delete a row
- `updateCell(rowIndex, header, value)`: Update cell value
- `addColumn()`: Add new column
- `removeColumn(header)`: Delete a column
- `updateHeaderName(oldHeader, newHeader)`: Rename column
- `confirmCSVEditor()`: Convert editor data to CSV and proceed
- `enableEditMode()`: Switch uploaded CSV to edit mode
- `saveEditedCSV()`: Save changes to uploaded CSV
- `cancelEdit()`: Cancel editing and return to view mode

## User Flow

1. User opens CSV Wizard
2. Either:
   - **Path A**: Upload CSV → View data → Click "Edit CSV" → Make changes → Save
   - **Path B**: Click "Create CSV" → Fill in table → Confirm
3. Proceed to style references step
4. Complete generation

## Benefits

- No external CSV editor needed
- Quick corrections without re-uploading
- Create small datasets on-the-fly
- Same familiar interface for both create and edit
- Maintains data validation and limits
