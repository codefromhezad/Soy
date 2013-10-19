<?php

Connection('local_database', 'mysql://username:pass@localhost/dbname');
Connection('remote_database', 'mysql://username:pass@remote_host.com/dbname');

Connection('local_files', 'file:///C:/path/to/project');
Connection('remote_server', 'ssh://username:password@remote_host.com/path/to/project');
