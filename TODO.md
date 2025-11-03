# SnapDraft - Project TODO List

**Last Updated**: November 3, 2025  
**Project Status**: AI Integration Complete - Ready for Testing & Frontend Integration

---

## 📋 Quick Reference

**What's Done:**
- ✅ Database (4 tables with relationships)
- ✅ All Controllers (Project, Image, Canvas, 3 Wizards)
- ✅ File Storage & Upload System
- ✅ Authorization (Policies)
- ✅ AI Service Implementation (Google Gemini with Style Mirror)
- ✅ Queue Jobs (All 4 jobs fully implemented)
- ✅ Canvas Editor Backend
- ✅ Loading States (Spinners, Skeletons, Progress bars)
- ✅ Empty States (No projects, No images, etc.)
- ✅ Lazy Loading Images
- ✅ Response Caching
- ✅ API Rate Limiting
- ✅ AI Integration (GoogleGeminiService with generateWithReferences)
- ✅ Job Dispatching (All wizard controllers trigger AI generation)
- ✅ Toast Notifications (Sonner library)
- ✅ Real-time Progress Tracking (Polling-based)
- ✅ Per-User Rate Limiting (Throttle middleware)
- ✅ Generate More Feature (POST /projects/{id}/generate)
- ✅ Admin Panel (Dashboard, User Management, Usage Monitoring)
- ✅ Subscription System (Free/Pro/Enterprise tiers)
- ✅ Credits System (Monthly allocation, usage tracking, enforcement)
- ✅ Billing Portal (Plans page, Billing portal, Purchase credits UI)

**What's Next:**
1. 🔴 **TEST AI FLOW**: Create project via wizard → Monitor queue → Verify images generated
2. 🟡 Integrate Paddle for payment processing
3. 🟡 Write comprehensive tests
3. � Add onboarding flow
4. � Final performance tuning
5. 🟢 Add keyboard shortcuts documentation

**Quick Start:**
- Run migrations: `php artisan migrate`
- Start queue: `php artisan queue:work`
- Start dev server: `composer dev`
- Add `GOOGLE_GEMINI_API_KEY` to `.env`
- Test via CSV wizard at `/csv-wizard`

**Documentation:**
- Backend Overview: `IMPLEMENTATION_SUMMARY.md`
- AI Integration: `AI_INTEGRATION.md`
- UX & Performance: `UX_PERFORMANCE_GUIDE.md`

---

## ✅ COMPLETED CRITICAL TASKS

All critical backend infrastructure AND AI integration are complete. The system can now:
- Accept project creation via 3 wizards (CSV, Images, Text)
- Dispatch AI generation jobs automatically
- Use Google Gemini 2.0 Flash with Style Mirror approach
- Generate brand-consistent images from reference images
- Save generated images with thumbnails
- Track token usage and costs

Ready for end-to-end testing and frontend progress tracking implementation.

---

## 🔴 CRITICAL - Must Complete Before Launch

### Database & Models
- [x] Create `projects` table migration
  - [x] Fields: id, user_id, title, description, settings (JSON), is_favorite, created_at, updated_at
  - [x] Add indexes on user_id, is_favorite, created_at
- [x] Create `images` table migration
  - [x] Fields: id, project_id, url, thumbnail_url, prompt, metadata (JSON), order, created_at, updated_at
  - [x] Add indexes on project_id, created_at
- [x] Create `brand_references` table migration
  - [x] Fields: id, project_id, url, analysis_data (JSON), created_at, updated_at
  - [x] Add index on project_id
- [x] Create `generation_history` table migration
  - [x] Fields: id, user_id, project_id, ai_model, tokens_used, cost, status, error_message, created_at
  - [x] Add indexes on user_id, project_id, created_at
- [x] Create Project model with relationships
  - [x] hasMany images
  - [x] hasMany brandReferences
  - [x] belongsTo user
  - [x] Scopes: favorites, recent
- [x] Create Image model with relationships
  - [x] belongsTo project
  - [x] Add image URL accessors
- [x] Create BrandReference model
  - [x] belongsTo project
- [x] Create GenerationHistory model
  - [x] belongsTo user
  - [x] belongsTo project

### Backend API - ProjectController
- [x] Implement `store()` method
  - [x] Validate input
  - [x] Create project record
  - [x] Handle brand reference uploads
- [x] Implement `update()` method
  - [x] Support title rename
  - [x] Support description update
  - [x] Update settings
- [x] Implement `destroy()` method
  - [x] Cascade delete images
  - [x] Delete associated files from storage
  - [x] Delete brand references
- [x] Add `toggleFavorite()` method
  - [x] POST /projects/{id}/toggle-favorite
  - [x] Update is_favorite field
  - [x] Return updated project
- [x] Add `generateMore()` method
  - [x] POST /projects/{id}/generate
  - [x] Queue generation job
  - [x] Return job status
- [x] Add `generationProgress()` method
  - [x] GET /projects/{id}/generation-progress
  - [x] Return progress statistics
  - [x] Track completed/failed/processing counts
- [x] Update `index()` with real data
  - [x] Query from database
  - [x] Add pagination (20 per page)
  - [x] Support filtering (all/recent/favorites)
  - [x] Support sorting options
- [x] Update `show()` with real data
  - [x] Eager load images
  - [x] Return full project with images array

### Backend API - ImageController
- [x] Create ImageController
- [x] Add `update()` method for image metadata
- [x] Add `destroy()` method
  - [x] Delete file from storage
  - [x] Delete database record
- [x] Add `bulkDestroy()` method
  - [x] Accept array of image IDs
  - [x] Validate ownership
  - [x] Delete multiple images
- [x] Add `bulkDownload()` method
  - [x] Generate ZIP file
  - [x] Stream download response
- [x] Add `updateOrder()` method
  - [x] Reorder images in project

### Wizard Backend Logic
- [x] Complete CSVWizardController
  - [x] `upload()` - Handle CSV file upload
  - [x] `parse()` - Parse and validate CSV structure
  - [x] `preview()` - Return first 5 rows for preview
  - [x] `generate()` - Queue generation jobs for each row (ready for AI)
  - [x] Store CSV data temporarily (cache or session)
- [x] Create ImagesWizardController
  - [x] `store()` - Create project with images workflow
  - [x] Handle brand reference uploads
  - [x] Validate image formats (jpg, png, webp)
  - [ ] Queue brand analysis job (AI integration pending)
- [x] Create TextWizardController
  - [x] `store()` - Create project from text description
  - [x] Handle optional reference images
  - [ ] Queue text-to-image generation job (AI integration pending)

### AI Integration
- [x] Create GoogleGeminiService
  - [x] `analyzeBrandStyle()` - Extract style from references (placeholder, ready for API implementation)
  - [x] `generateImage()` - Generate image from prompt (placeholder, ready for API implementation)
  - [x] Error handling and retry logic (via AIServiceManager)
  - [x] Response parsing and validation structure
- [x] Create OpenRouterService (fallback)
  - [x] Same methods as Gemini
  - [x] Model selection logic
- [x] Create AIServiceManager
  - [x] Route requests to primary/fallback service
  - [x] Track usage and costs (via GenerationHistory)
- [x] Add AI configuration to config/services.php
  - [x] Gemini API key
  - [x] OpenRouter API key
  - [x] Rate limits
  - [x] Model preferences
- [x] Create queue jobs
  - [x] `AnalyzeBrandStyleJob`
  - [x] `GenerateSingleImageJob`
  - [x] `GenerateBatchImagesJob`
  - [x] Handle job failures gracefully
- [x] Implement rate limiting
  - [x] 2-second delay between generations (in GenerateBatchImagesJob)
  - [x] Per-user limits (ThrottlePerUser middleware - 10 req/min for AI gen)
  - [x] Queue throttling (via job delays)

### File Storage & Management
- [x] Configure storage disk in config/filesystems.php
  - [x] Local disk for development
  - [x] S3/CloudFlare R2 for production (config ready)
- [x] Create FileUploadService
  - [x] Validate file types and sizes
  - [x] Store uploaded files
  - [x] Generate thumbnails
  - [x] Return storage paths
- [x] Implement image optimization
  - [x] Compress uploaded images
  - [x] Generate multiple sizes (thumbnail, medium, full)
  - [x] WebP conversion for better performance (via Intervention Image)
- [x] Create file cleanup job
  - [x] `CleanOrphanedFilesJob`
  - [x] Run daily via scheduler (scheduled at 2 AM)
  - [x] Delete files not linked to database records

### Canvas Editor Backend
- [x] Create CanvasController
- [x] Add `saveEdit()` method
  - [x] POST /projects/{id}/images/{imageId}/save-edit
  - [x] Accept canvas data (base64 encoded image)
  - [x] Generate new image file with thumbnail
  - [x] Create new image record or update existing
- [x] Add `exportCanvas()` method
  - [x] Generate final image from canvas state
  - [x] Return download URL
  - [x] Support multiple formats (PNG, JPG, WebP)

### Authentication & Authorization
- [x] Add auth middleware to all project routes
- [x] Create ProjectPolicy
  - [x] `view()` - User owns project
  - [x] `update()` - User owns project
  - [x] `delete()` - User owns project
- [x] Create ImagePolicy
  - [x] Check via project ownership
- [x] Add authorization to ProjectController
  - [x] show(), edit(), update(), destroy(), toggleFavorite()
- [x] Add authorization to ImageController
  - [x] update(), destroy(), bulkDestroy(), bulkDownload(), updateOrder(), toggleFavorite()
- [x] Add authorization to Wizard controllers
  - [x] CSVWizardController, ImagesWizardController, TextWizardController
- [x] Add API rate limiting
  - [x] Throttle API endpoints (60 requests/min)
  - [x] Middleware configured in bootstrap/app.php
  - [x] Per-user rate limiting for AI generation (10 req/min)
- [x] Add usage limits
  - [x] Track generations per user
  - [x] Enforce subscription limits (credits system)
  - [x] Block generation when no credits

---

## 🟡 IMPORTANT - Should Complete Before Launch

### User Experience Improvements
- [x] Add real loading states
  - [x] Spinner component created
  - [x] Loading overlay for async operations
  - [x] Skeleton loaders for project cards
- [x] Improve error messages
  - [x] Flash message handler component (using Sonner toasts)
  - [x] Install toast library (sonner)
  - [x] Empty states for no projects, no images, upload failures
- [x] Add empty states
  - [x] "No projects yet" with CTA
  - [x] "No images in project" guide
  - [x] "Upload failed" with retry button
  - [x] No search results component
- [x] Add batch progress tracking
  - [x] Real-time progress component (BatchProgress)
  - [x] useGenerationProgress hook with polling
  - [x] Display in project detail page
  - [x] Show completed/failed/processing stats
- [ ] Create onboarding flow
  - [ ] Welcome modal on first login
  - [ ] Feature tour (optional)
  - [ ] Sample project creation
- [ ] Add keyboard shortcuts documentation
  - [ ] Help modal (press ?)
  - [ ] Show shortcuts in canvas editor
  - [ ] Tooltips on hover

### Data Validation
- [x] Add validation to CSV wizard
  - [x] Required columns (title, description, format)
  - [x] Max file size (10MB)
  - [x] Max rows (100 for free tier) - stored in settings
- [x] Add validation to Images wizard
  - [x] 5-10 brand references required
  - [x] File types (jpg, png, webp only)
  - [x] Max 5MB per image (10MB implemented)
- [x] Add validation to Text wizard
  - [x] Min/max text length
  - [x] Optional references (0-5 images)
- [x] Server-side validation for all forms
  - [x] Use Form Requests (StoreProjectRequest, UpdateProjectRequest)
  - [x] Return validation errors to frontend
- [x] Sanitize all user inputs
  - [x] Strip HTML tags from text inputs (handled by validation)
  - [x] Validate file uploads server-side (FileUploadService)

### Performance Optimizations
- [x] Add pagination to projects list
  - [x] 20 projects per page
  - [x] Inertia pagination component
  - [ ] Remember scroll position (needs implementation)
- [x] Implement lazy loading for images
  - [x] LazyImage component with intersection observer
  - [x] Load images as they enter viewport
  - [x] LazyImageGrid for optimized grids
- [x] Generate image thumbnails
  - [x] Small (400x400) for cards via FileUploadService
  - [x] Automatic generation on upload
  - [x] Full size for canvas editor
- [x] Add caching
  - [x] CacheResponse middleware for GET requests
  - [ ] Cache AI analysis results (needs implementation in jobs)
  - [ ] Cache user preferences (needs implementation)
- [x] Optimize database queries
  - [x] Eager load relationships
  - [x] Add database indexes (all migrations have indexes)
  - [x] Use select() to limit columns
- [x] Add API rate limiting
  - [x] Throttle API endpoints (60 requests/min)
  - [x] Middleware configured in bootstrap/app.php

### Testing
- [ ] Write feature tests
  - [ ] Project CRUD operations
  - [ ] Wizard workflows
  - [ ] File uploads
  - [ ] Authentication flows
- [ ] Write unit tests
  - [ ] AI service classes
  - [ ] File upload service
  - [ ] Image generation logic
- [ ] Test error handling
  - [ ] API failures
  - [ ] Network errors
  - [ ] Invalid inputs
- [ ] Test performance
  - [ ] Large CSV files
  - [ ] Batch generation
  - [ ] Concurrent users

---

## 🟢 NICE-TO-HAVE - Post-Launch Features

### Collaboration Features
- [ ] Share projects with team members
  - [ ] Generate shareable links
  - [ ] Set permissions (view/edit)
  - [ ] Email invitations
- [ ] Add comments on images
  - [ ] Threaded discussions
  - [ ] @mentions
  - [ ] Notifications
- [ ] Create project templates
  - [ ] Save project as template
  - [ ] Browse template library
  - [ ] One-click project creation from template

### Advanced Features
- [ ] Batch editing for images
  - [ ] Apply same filter to multiple
  - [ ] Bulk resize/crop
  - [ ] Bulk export
- [ ] A/B testing features
  - [ ] Generate variations
  - [ ] Side-by-side comparison
  - [ ] Vote on favorites
- [ ] Analytics dashboard
  - [ ] Most used features
  - [ ] Generation success rate
  - [ ] Cost tracking
  - [ ] User engagement metrics
- [ ] Export integrations
  - [ ] Direct export to Figma
  - [ ] Canva integration
  - [ ] Google Drive sync
  - [ ] Dropbox sync

### Admin Features
- [x] Create admin panel
- [x] User management
  - [x] View all users
  - [x] Suspend/activate accounts
  - [x] View user usage
  - [x] Change user subscription tiers
  - [x] Delete users
- [x] Usage monitoring
  - [x] Track API calls
  - [x] Monitor costs
  - [x] Alert on high usage
  - [x] View platform statistics
- [ ] Feature flags system
  - [ ] Enable/disable features
  - [ ] A/B test new features
  - [ ] Gradual rollouts

### Subscription & Billing
- [ ] Integrate payment gateway (Paddle) - **PENDING: Awaiting Paddle setup**
- [x] Create subscription plans
  - [x] Free tier (10 generations/month)
  - [x] Pro tier (100 generations/month)
  - [x] Enterprise tier (unlimited)
- [x] Add billing portal
  - [x] View invoices
  - [x] Update payment method UI
  - [x] Cancel subscription (downgrade to free)
  - [ ] Paddle payment integration (pending)
- [x] Usage-based pricing
  - [x] Track AI credits
  - [x] Purchase additional credits (UI ready, Paddle integration pending)
  - [ ] Auto-recharge options (future)

---

## 📋 Current Sprint (Week of Nov 3, 2025)

### Completed
- [x] List view implementation for projects page
- [x] Double-click to rename project titles
- [x] Add "Generate More" button to project cards
- [x] Implement quick actions (favorite, edit, delete) - UI only
- [x] **Create database migrations (projects, images, brand_references, generation_history)**
- [x] **Implement Project, Image, BrandReference, GenerationHistory models**
- [x] **Complete ProjectController backend methods (CRUD + toggleFavorite)**
- [x] **Create ImageController with bulk operations**
- [x] **Connect frontend to real backend APIs**
- [x] **Configure file storage and create symbolic link**
- [x] **Create FileUploadService with thumbnail generation**
- [x] **Implement all 3 wizard controllers (CSV, Images, Text)**
- [x] **Add Form Request validation (StoreProjectRequest, UpdateProjectRequest)**
- [x] **Add pagination to projects list (20 per page)**
- [x] **Install Intervention Image library**

### Up Next
- [ ] Create Policy classes for authorization
- [ ] Add loading states to frontend
- [ ] Implement error handling and user-friendly messages
- [ ] Create canvas editor save functionality
- [ ] **Prepare AI integration structure (jobs, services)**

---

## 🐛 Known Issues & Bugs

- [x] Projects page uses mock data (hardcoded array) - FIXED: Now uses database
- [x] Quick actions (favorite, edit, delete) only log to console - FIXED: Now functional
- [ ] Canvas editor changes not persisted
- [ ] No authentication guards on project routes (partially done - needs testing)
- [ ] Missing validation on all forms
- [ ] No error handling for failed operations

---

## 📝 Notes & Decisions

- **AI Service**: Using Google Gemini as primary, OpenRouter as fallback
- **File Storage**: Start with local, migrate to S3 for production
- **Queue**: Using Laravel queue system for batch operations
- **Rate Limiting**: 2-second delay between generations (per requirements)
- **Image Formats**: Support JPG, PNG, WebP for uploads
- **CSV Format**: Required columns: title, description, format

---

## 🎯 Success Metrics for Launch

- [ ] 100% of core features implemented
- [ ] All critical tests passing
- [ ] Page load time < 2 seconds
- [ ] AI generation success rate > 95%
- [ ] Zero critical security vulnerabilities
- [ ] Mobile responsive on all pages
- [ ] Documentation complete (README, API docs)

---

**Next Review Date**: November 10, 2025  
**Target Launch Date**: December 15, 2025
