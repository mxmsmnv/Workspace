<?php namespace ProcessWire;

/** Optional compatibility descriptors; no application is installed or granted access here. */
return [
    ['id'=>'kontor', 'title'=>
        __('Kontor', Workspace::translationFile('integrations/built-in.php')), 'description'=>
        __('Customers, sales and business operations.', Workspace::translationFile('integrations/built-in.php')), 'icon'=>'briefcase', 'color'=>'#4f46e5', 'module'=>'Kontor', 'process'=>'ProcessKontor', 'navigationKey'=>'kontorNavigation', 'allowedSections'=>['contacts','companies','catalog','crm','sales','invoices','projects','tasks']],
    ['id'=>'mercato', 'title'=>
        __('Mercato', Workspace::translationFile('integrations/built-in.php')), 'description'=>
        __('Products, customers and orders.', Workspace::translationFile('integrations/built-in.php')), 'icon'=>'shopping-bag', 'color'=>'#d97706', 'module'=>'Mercato', 'process'=>'ProcessMercato'],
    ['id'=>'mailbox', 'title'=>
        __('Mailbox', Workspace::translationFile('integrations/built-in.php')), 'description'=>
        __('Your email accounts and conversations.', Workspace::translationFile('integrations/built-in.php')), 'icon'=>'mail', 'color'=>'#2563eb', 'module'=>'Mailbox', 'process'=>'ProcessMailbox'],
    ['id'=>'dispatch', 'title'=>
        __('Dispatch', Workspace::translationFile('integrations/built-in.php')), 'description'=>
        __('Campaigns and audience communication.', Workspace::translationFile('integrations/built-in.php')), 'icon'=>'send', 'color'=>'#db2777', 'module'=>'Dispatch', 'process'=>'ProcessDispatch'],
    ['id'=>'files', 'title'=>
        __('Files', Workspace::translationFile('integrations/built-in.php')), 'description'=>
        __('Private files, folders and sharing.', Workspace::translationFile('integrations/built-in.php')), 'icon'=>'folder', 'color'=>'#059669', 'module'=>'Files', 'process'=>'ProcessFiles'],
    ['id'=>'tickets', 'title'=>
        __('Tickets', Workspace::translationFile('integrations/built-in.php')), 'description'=>
        __('Customer questions and support.', Workspace::translationFile('integrations/built-in.php')), 'icon'=>'life-buoy', 'color'=>'#7c3aed', 'module'=>'Tickets', 'process'=>'ProcessTickets'],
];
