<?php
/**
 * Declarative definitions for everything the admin panel manages.
 *
 * Each resource describes its table, the columns shown in the list view and
 * the fields shown in the form. admin/manage.php, admin/edit.php and
 * admin/delete.php are generic and read these definitions, so adding a new
 * managed table means adding one entry here — not three new files.
 *
 * Field types: text, textarea, email, url, tel, number, date, time, select,
 *              checkbox, image, slug, readonly
 */

function admin_resources(PDO $pdo): array
{
    // Option lists that come from the database are built once here.
    $stateOptions = [];
    foreach (active_states($pdo) as $state) {
        $stateOptions[$state['name']] = $state['name'];
    }

    $programmeOptions = ['' => '— none —'];
    foreach (active_programmes($pdo) as $programme) {
        $programmeOptions[$programme['id']] = $programme['title'];
    }

    $outreachOptions = ['' => '— none —'];
    foreach ($pdo->query('SELECT id, title FROM outreaches ORDER BY outreach_date DESC') as $row) {
        $outreachOptions[$row['id']] = $row['title'];
    }

    return [

        // -------------------------------------------------------------------
        'volunteers' => [
            'label'    => 'Volunteer sign-ups',
            'singular' => 'Volunteer sign-up',
            'icon'     => 'users',
            'table'    => 'volunteers',
            'order'    => 'created_at DESC',
            'group'    => 'Inbox',
            'can_create' => false,
            'export'   => true,
            'search'   => ['full_name', 'email', 'phone', 'state', 'city'],
            'list'     => [
                'full_name' => 'Name',
                'state'     => 'State',
                'phone'     => 'Phone',
                'status'    => 'Status',
                'created_at'=> 'Registered',
            ],
            'fields' => [
                'full_name'    => ['label' => 'Full name',   'type' => 'text',     'required' => true],
                'email'        => ['label' => 'Email',       'type' => 'email',    'required' => true],
                'phone'        => ['label' => 'Phone',       'type' => 'tel'],
                'state'        => ['label' => 'State',       'type' => 'text'],
                'city'         => ['label' => 'Town / city', 'type' => 'text'],
                'occupation'   => ['label' => 'Occupation',  'type' => 'text'],
                'interests'    => ['label' => 'Programmes of interest', 'type' => 'text', 'full' => true],
                'availability' => ['label' => 'Availability','type' => 'text'],
                'status'       => ['label' => 'Status',      'type' => 'select', 'options' => [
                    'new' => 'New', 'contacted' => 'Contacted', 'active' => 'Active volunteer', 'archived' => 'Archived',
                ]],
                'skills'       => ['label' => 'Skills',      'type' => 'textarea', 'full' => true],
                'motivation'   => ['label' => 'Why they want to volunteer', 'type' => 'textarea', 'full' => true],
                'heard_from'   => ['label' => 'Heard about us via', 'type' => 'text'],
                'created_at'   => ['label' => 'Registered',  'type' => 'readonly'],
            ],
        ],

        // -------------------------------------------------------------------
        'messages' => [
            'label'    => 'Contact messages',
            'singular' => 'Message',
            'icon'     => 'mail',
            'table'    => 'contact_messages',
            'order'    => 'created_at DESC',
            'group'    => 'Inbox',
            'can_create' => false,
            'export'   => true,
            'search'   => ['name', 'email', 'subject', 'message'],
            'list'     => [
                'name'       => 'From',
                'subject'    => 'Subject',
                'email'      => 'Email',
                'is_read'    => 'Read',
                'created_at' => 'Received',
            ],
            'fields' => [
                'name'       => ['label' => 'From',    'type' => 'text', 'required' => true],
                'email'      => ['label' => 'Email',   'type' => 'email', 'required' => true],
                'phone'      => ['label' => 'Phone',   'type' => 'tel'],
                'subject'    => ['label' => 'Subject', 'type' => 'text'],
                'is_read'    => ['label' => 'Marked as read', 'type' => 'checkbox'],
                'message'    => ['label' => 'Message', 'type' => 'textarea', 'full' => true, 'rows' => 10],
                'created_at' => ['label' => 'Received','type' => 'readonly'],
            ],
        ],

        // -------------------------------------------------------------------
        'outreaches' => [
            'label'    => 'Outreaches',
            'singular' => 'Outreach',
            'icon'     => 'pin',
            'table'    => 'outreaches',
            'order'    => 'outreach_date DESC',
            'group'    => 'Content',
            'search'   => ['title', 'summary', 'location', 'state'],
            'list'     => [
                'title'         => 'Title',
                'outreach_date' => 'Date',
                'state'         => 'State',
                'beneficiaries' => 'Reached',
                'volunteers'    => 'Volunteers',
            ],
            'fields' => [
                'title'         => ['label' => 'Title', 'type' => 'text', 'required' => true, 'full' => true],
                'slug'          => ['label' => 'URL slug', 'type' => 'slug', 'from' => 'title',
                                    'hint' => 'Used in the page address. Leave blank to generate it from the title.'],
                'outreach_date' => ['label' => 'Date', 'type' => 'date', 'required' => true],
                'location'      => ['label' => 'Location', 'type' => 'text'],
                'state'         => ['label' => 'State', 'type' => 'select', 'options' => $stateOptions, 'allow_other' => true],
                'programme_id'  => ['label' => 'Programme', 'type' => 'select', 'options' => $programmeOptions],
                'beneficiaries' => ['label' => 'People reached', 'type' => 'number'],
                'volunteers'    => ['label' => 'Volunteers involved', 'type' => 'number'],
                'sort_order'    => ['label' => 'Sort order', 'type' => 'number'],
                'cover_image'   => ['label' => 'Cover photo', 'type' => 'image', 'full' => true,
                                    'hint' => 'Leave empty to use the generated illustration.'],
                'summary'       => ['label' => 'Summary', 'type' => 'textarea', 'full' => true, 'rows' => 3,
                                    'hint' => 'One or two sentences, shown on cards and listings.'],
                'story'         => ['label' => 'Full story', 'type' => 'textarea', 'full' => true, 'rows' => 12,
                                    'hint' => 'Leave a blank line between paragraphs.'],
            ],
        ],

        // -------------------------------------------------------------------
        'outreach_photos' => [
            'label'    => 'Outreach photos',
            'singular' => 'Outreach photo',
            'icon'     => 'sparkle',
            'table'    => 'outreach_photos',
            'order'    => 'outreach_id, sort_order',
            'group'    => 'Content',
            'search'   => ['caption'],
            'list'     => [
                'image'      => 'Photo',
                'caption'    => 'Caption',
                'outreach_id'=> 'Outreach',
                'sort_order' => 'Order',
            ],
            'labels'   => ['outreach_id' => $outreachOptions],
            'fields' => [
                'outreach_id' => ['label' => 'Belongs to outreach', 'type' => 'select',
                                  'options' => $outreachOptions, 'required' => true],
                'sort_order'  => ['label' => 'Sort order', 'type' => 'number'],
                'caption'     => ['label' => 'Caption', 'type' => 'text', 'full' => true],
                'image'       => ['label' => 'Photo', 'type' => 'image', 'full' => true],
            ],
        ],

        // -------------------------------------------------------------------
        'gallery' => [
            'label'    => 'Gallery',
            'singular' => 'Gallery photo',
            'icon'     => 'sparkle',
            'table'    => 'gallery_items',
            'order'    => 'sort_order, id',
            'group'    => 'Content',
            'search'   => ['title', 'caption', 'category', 'state'],
            'list'     => [
                'image'    => 'Photo',
                'title'    => 'Title',
                'category' => 'Category',
                'state'    => 'State',
                'taken_on' => 'Taken',
            ],
            'fields' => [
                'title'       => ['label' => 'Title', 'type' => 'text', 'required' => true],
                'category'    => ['label' => 'Category', 'type' => 'select', 'allow_other' => true, 'options' => [
                    'Education' => 'Education', 'Health' => 'Health', 'Skills' => 'Skills',
                    'Relief' => 'Relief', 'Volunteers' => 'Volunteers', 'Events' => 'Events',
                ], 'hint' => 'Becomes a filter button on the gallery page.'],
                'state'       => ['label' => 'State', 'type' => 'select', 'options' => $stateOptions, 'allow_other' => true],
                'taken_on'    => ['label' => 'Date taken', 'type' => 'date'],
                'outreach_id' => ['label' => 'From outreach', 'type' => 'select', 'options' => $outreachOptions],
                'sort_order'  => ['label' => 'Sort order', 'type' => 'number'],
                'image'       => ['label' => 'Photo', 'type' => 'image', 'full' => true],
                'caption'     => ['label' => 'Caption', 'type' => 'textarea', 'full' => true, 'rows' => 3],
            ],
        ],

        // -------------------------------------------------------------------
        'volunteer_profiles' => [
            'label'    => 'Volunteer profiles',
            'singular' => 'Volunteer profile',
            'icon'     => 'heart',
            'table'    => 'volunteer_profiles',
            'order'    => 'sort_order, id',
            'group'    => 'Content',
            'search'   => ['full_name', 'role', 'state', 'outreach'],
            'list'     => [
                'photo'     => 'Photo',
                'full_name' => 'Name',
                'role'      => 'Role',
                'state'     => 'State',
                'is_active' => 'Shown',
            ],
            'fields' => [
                'full_name'     => ['label' => 'Full name', 'type' => 'text', 'required' => true],
                'role'          => ['label' => 'Role', 'type' => 'text'],
                'state'         => ['label' => 'State', 'type' => 'select', 'options' => $stateOptions, 'allow_other' => true],
                'serving_since' => ['label' => 'Serving since (year)', 'type' => 'number'],
                'outreach'      => ['label' => 'Known from outreach', 'type' => 'text', 'full' => true],
                'sort_order'    => ['label' => 'Sort order', 'type' => 'number'],
                'is_active'     => ['label' => 'Show on the volunteer page', 'type' => 'checkbox'],
                'photo'         => ['label' => 'Photograph', 'type' => 'image', 'full' => true,
                                    'hint' => 'Square images work best. Leave empty for a generated portrait.'],
                'quote'         => ['label' => 'Quote', 'type' => 'textarea', 'full' => true, 'rows' => 3],
            ],
        ],

        // -------------------------------------------------------------------
        'events' => [
            'label'    => 'Events',
            'singular' => 'Event',
            'icon'     => 'calendar',
            'table'    => 'events',
            'order'    => 'event_date DESC',
            'group'    => 'Content',
            'search'   => ['title', 'summary', 'location'],
            'list'     => [
                'title'       => 'Title',
                'event_date'  => 'Date',
                'location'    => 'Location',
                'is_featured' => 'Featured',
            ],
            'fields' => [
                'title'       => ['label' => 'Title', 'type' => 'text', 'required' => true, 'full' => true],
                'slug'        => ['label' => 'URL slug', 'type' => 'slug', 'from' => 'title'],
                'event_date'  => ['label' => 'Date', 'type' => 'date', 'required' => true],
                'start_time'  => ['label' => 'Start time', 'type' => 'time'],
                'location'    => ['label' => 'Location', 'type' => 'text'],
                'state'       => ['label' => 'State', 'type' => 'select', 'options' => $stateOptions, 'allow_other' => true],
                'cta_label'   => ['label' => 'Button label', 'type' => 'text',
                                  'hint' => 'e.g. "Register to attend". Defaults to "Ask about this".'],
                'is_featured' => ['label' => 'Feature at the top of the events page', 'type' => 'checkbox'],
                'summary'     => ['label' => 'Summary', 'type' => 'textarea', 'full' => true, 'rows' => 3],
                'description' => ['label' => 'Full description', 'type' => 'textarea', 'full' => true, 'rows' => 10],
            ],
        ],

        // -------------------------------------------------------------------
        'programmes' => [
            'label'    => 'Programmes',
            'singular' => 'Programme',
            'icon'     => 'sparkle',
            'table'    => 'programmes',
            'order'    => 'sort_order, id',
            'group'    => 'Content',
            'search'   => ['title', 'tagline'],
            'list'     => [
                'title'      => 'Title',
                'tagline'    => 'Tagline',
                'sort_order' => 'Order',
                'is_active'  => 'Shown',
            ],
            'fields' => [
                'title'       => ['label' => 'Title', 'type' => 'text', 'required' => true],
                'slug'        => ['label' => 'URL slug', 'type' => 'slug', 'from' => 'title'],
                'icon'        => ['label' => 'Illustration', 'type' => 'select', 'options' => [
                    'education' => 'Education (graduation cap)', 'health' => 'Health (heart)',
                    'skills' => 'Skills (laptop)', 'relief' => 'Relief (food basket)', 'people' => 'People (volunteers)', 'heart' => 'Generic heart',
                ]],
                'accent'      => ['label' => 'Accent colour', 'type' => 'select', 'options' => [
                    'brand' => 'Green', 'coral' => 'Coral', 'sky' => 'Blue', 'sun' => 'Amber',
                ]],
                'sort_order'  => ['label' => 'Sort order', 'type' => 'number'],
                'is_active'   => ['label' => 'Show on the site', 'type' => 'checkbox'],
                'tagline'     => ['label' => 'Tagline', 'type' => 'text', 'full' => true],
                'description' => ['label' => 'Description', 'type' => 'textarea', 'full' => true, 'rows' => 8],
            ],
        ],

        // -------------------------------------------------------------------
        'states' => [
            'label'    => 'States / chapters',
            'singular' => 'State',
            'icon'     => 'pin',
            'table'    => 'states',
            'order'    => 'sort_order, name',
            'group'    => 'Content',
            'search'   => ['name', 'hub_city'],
            'list'     => [
                'name'       => 'State',
                'hub_city'   => 'Hub city',
                'volunteers' => 'Volunteers',
                'is_active'  => 'Shown',
            ],
            'fields' => [
                'name'       => ['label' => 'State name', 'type' => 'text', 'required' => true,
                                 'hint' => 'Use "Abuja" for the FCT — the map plots Lagos, Ogun, Oyo, Osun, Kwara, Abuja, Benue and Rivers by name.'],
                'code'       => ['label' => 'Short code', 'type' => 'text'],
                'hub_city'   => ['label' => 'Hub city', 'type' => 'text'],
                'volunteers' => ['label' => 'Volunteer count', 'type' => 'number'],
                'sort_order' => ['label' => 'Sort order', 'type' => 'number'],
                'is_active'  => ['label' => 'Show on the site', 'type' => 'checkbox'],
                'note'       => ['label' => 'Note', 'type' => 'text', 'full' => true],
            ],
        ],

        // -------------------------------------------------------------------
        'team' => [
            'label'    => 'Team members',
            'singular' => 'Team member',
            'icon'     => 'users',
            'table'    => 'team_members',
            'order'    => 'sort_order, id',
            'group'    => 'Content',
            'search'   => ['name', 'role'],
            'list'     => [
                'photo'     => 'Photo',
                'name'      => 'Name',
                'role'      => 'Role',
                'is_active' => 'Shown',
            ],
            'fields' => [
                'name'       => ['label' => 'Name', 'type' => 'text', 'required' => true],
                'role'       => ['label' => 'Role', 'type' => 'text'],
                'sort_order' => ['label' => 'Sort order', 'type' => 'number'],
                'is_active'  => ['label' => 'Show on the about page', 'type' => 'checkbox'],
                'photo'      => ['label' => 'Photograph', 'type' => 'image', 'full' => true],
                'bio'        => ['label' => 'Short bio', 'type' => 'textarea', 'full' => true, 'rows' => 4],
            ],
        ],

        // -------------------------------------------------------------------
        'stats' => [
            'label'    => 'Impact numbers',
            'singular' => 'Impact number',
            'icon'     => 'sparkle',
            'table'    => 'impact_stats',
            'order'    => 'sort_order, id',
            'group'    => 'Content',
            'search'   => ['label'],
            'list'     => ['label' => 'Label', 'value' => 'Value', 'suffix' => 'Suffix', 'sort_order' => 'Order'],
            'fields'   => [
                'label'      => ['label' => 'Label', 'type' => 'text', 'required' => true, 'full' => true],
                'value'      => ['label' => 'Number', 'type' => 'number', 'required' => true],
                'suffix'     => ['label' => 'Suffix', 'type' => 'text', 'hint' => 'e.g. "+" or "%"'],
                'icon'       => ['label' => 'Illustration', 'type' => 'select', 'options' => [
                    'heart' => 'Heart', 'education' => 'Education', 'health' => 'Health',
                    'skills' => 'Skills', 'relief' => 'Relief',
                ]],
                'sort_order' => ['label' => 'Sort order', 'type' => 'number'],
            ],
        ],

        // -------------------------------------------------------------------
        'stories' => [
            'label'    => 'Stories',
            'singular' => 'Story',
            'icon'     => 'quote',
            'table'    => 'stories',
            'order'    => 'is_approved, sort_order, id DESC',
            'group'    => 'Inbox',
            'export'   => true,
            'search'   => ['author_name', 'role', 'state', 'story'],
            'list'     => [
                'author_name' => 'Author',
                'role'        => 'Role',
                'state'       => 'State',
                'is_approved' => 'Published',
                'created_at'  => 'Received',
            ],
            'fields' => [
                'author_name' => ['label' => 'Author name', 'type' => 'text', 'required' => true],
                'role'        => ['label' => 'Role', 'type' => 'text',
                                  'hint' => 'e.g. Head Teacher, or Volunteer.'],
                'state'       => ['label' => 'State', 'type' => 'select', 'options' => $stateOptions, 'allow_other' => true],
                'email'       => ['label' => 'Email', 'type' => 'email',
                                  'hint' => 'For contacting them only. Never shown on the site.'],
                'sort_order'  => ['label' => 'Sort order', 'type' => 'number'],
                'is_approved' => ['label' => 'Publish on the stories page', 'type' => 'checkbox',
                                  'hint' => 'Nothing submitted through the website appears until you tick this.'],
                'photo'       => ['label' => 'Photograph', 'type' => 'image', 'full' => true],
                'story'       => ['label' => 'Story', 'type' => 'textarea', 'full' => true, 'rows' => 8, 'required' => true],
                'created_at'  => ['label' => 'Received', 'type' => 'readonly'],
            ],
        ],

        // -------------------------------------------------------------------
        'pledges' => [
            'label'    => 'Support pledges',
            'singular' => 'Pledge',
            'icon'     => 'heart',
            'table'    => 'pledges',
            'order'    => 'created_at DESC',
            'group'    => 'Inbox',
            'can_create' => false,
            'export'   => true,
            'search'   => ['name', 'email', 'organisation', 'support_type'],
            'list'     => [
                'name'         => 'Name',
                'organisation' => 'Organisation',
                'support_type' => 'Offering',
                'status'       => 'Status',
                'created_at'   => 'Received',
            ],
            'fields' => [
                'name'         => ['label' => 'Name', 'type' => 'text', 'required' => true],
                'email'        => ['label' => 'Email', 'type' => 'email', 'required' => true],
                'phone'        => ['label' => 'Phone', 'type' => 'tel'],
                'organisation' => ['label' => 'Organisation', 'type' => 'text'],
                'support_type' => ['label' => 'What they are offering', 'type' => 'text'],
                'status'       => ['label' => 'Status', 'type' => 'select', 'options' => [
                    'new' => 'New', 'contacted' => 'Contacted', 'active' => 'Active supporter', 'closed' => 'Closed',
                ]],
                'message'      => ['label' => 'Message', 'type' => 'textarea', 'full' => true, 'rows' => 6],
                'created_at'   => ['label' => 'Received', 'type' => 'readonly'],
            ],
        ],

        // -------------------------------------------------------------------
        'newsletter' => [
            'label'    => 'Newsletter list',
            'singular' => 'Subscriber',
            'icon'     => 'mail',
            'table'    => 'newsletter_subscribers',
            'order'    => 'created_at DESC',
            'group'    => 'Inbox',
            'can_create' => false,
            'export'   => true,
            'search'   => ['email', 'name'],
            'list'     => [
                'email'      => 'Email',
                'name'       => 'Name',
                'source'     => 'Signed up via',
                'is_active'  => 'Subscribed',
                'created_at' => 'Joined',
            ],
            'fields' => [
                'email'      => ['label' => 'Email', 'type' => 'email', 'required' => true],
                'name'       => ['label' => 'Name', 'type' => 'text'],
                'source'     => ['label' => 'Signed up via', 'type' => 'text'],
                'is_active'  => ['label' => 'Subscribed', 'type' => 'checkbox'],
                'created_at' => ['label' => 'Joined', 'type' => 'readonly'],
            ],
        ],

        // -------------------------------------------------------------------
        'faqs' => [
            'label'    => 'FAQs',
            'singular' => 'FAQ',
            'icon'     => 'search',
            'table'    => 'faqs',
            'order'    => 'category, sort_order, id',
            'group'    => 'Content',
            'search'   => ['question', 'answer', 'category'],
            'list'     => [
                'question'   => 'Question',
                'category'   => 'Category',
                'sort_order' => 'Order',
                'is_active'  => 'Shown',
            ],
            'fields' => [
                'question'   => ['label' => 'Question', 'type' => 'text', 'required' => true, 'full' => true],
                'category'   => ['label' => 'Category', 'type' => 'select', 'allow_other' => true, 'options' => [
                    'Volunteering' => 'Volunteering', 'Our work' => 'Our work',
                    'Money' => 'Money', 'General' => 'General',
                ], 'hint' => 'Groups the questions into sections on the FAQ page.'],
                'sort_order' => ['label' => 'Sort order', 'type' => 'number'],
                'is_active'  => ['label' => 'Show on the site', 'type' => 'checkbox'],
                'answer'     => ['label' => 'Answer', 'type' => 'textarea', 'full' => true, 'rows' => 6, 'required' => true],
            ],
        ],

        // -------------------------------------------------------------------
        'milestones' => [
            'label'    => 'Milestones',
            'singular' => 'Milestone',
            'icon'     => 'calendar',
            'table'    => 'milestones',
            'order'    => 'year, sort_order, id',
            'group'    => 'Content',
            'search'   => ['title', 'description'],
            'list'     => [
                'year'      => 'Year',
                'title'     => 'Milestone',
                'is_active' => 'Shown',
            ],
            'fields' => [
                'year'        => ['label' => 'Year', 'type' => 'number', 'required' => true],
                'title'       => ['label' => 'Title', 'type' => 'text', 'required' => true],
                'sort_order'  => ['label' => 'Sort order', 'type' => 'number',
                                  'hint' => 'Only matters when two milestones share a year.'],
                'is_active'   => ['label' => 'Show on the about page', 'type' => 'checkbox'],
                'description' => ['label' => 'Description', 'type' => 'textarea', 'full' => true, 'rows' => 4],
            ],
        ],

        // -------------------------------------------------------------------
        'partners' => [
            'label'    => 'Partners',
            'singular' => 'Partner',
            'icon'     => 'users',
            'table'    => 'partners',
            'order'    => 'sort_order, id',
            'group'    => 'Content',
            'search'   => ['name'],
            'list'     => ['name' => 'Name', 'url' => 'Website', 'sort_order' => 'Order'],
            'fields'   => [
                'name'       => ['label' => 'Name', 'type' => 'text', 'required' => true, 'full' => true],
                'url'        => ['label' => 'Website', 'type' => 'url', 'full' => true],
                'sort_order' => ['label' => 'Sort order', 'type' => 'number'],
            ],
        ],

    ];
}

function admin_resource(PDO $pdo, string $key): ?array
{
    $all = admin_resources($pdo);
    if (!isset($all[$key])) {
        return null;
    }
    $resource = $all[$key];
    $resource['key'] = $key;
    return $resource;
}
