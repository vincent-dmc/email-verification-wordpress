<?php


namespace HardBounceCleaner;


/**
 * Class EmailVerification
 * @package HardBounceCleaner
 */
class EmailVerification {

	/**
	 *
	 */
	public static function cron_hourly() {

		if ( ! defined( 'EVH_PLUGIN_START_TIME' ) ) {
			define( 'EVH_PLUGIN_START_TIME', time() );
		}
		if ( ! defined( 'EVH_PLUGIN_MAX_EXECUTION_TIME' ) ) {
			$max_execution_time = ( 60 * 20 );
			ini_set( 'max_execution_time', $max_execution_time );
			set_time_limit( $max_execution_time );
			// get the max_execution_time value in case of safe mode
			define( 'EVH_PLUGIN_MAX_EXECUTION_TIME', (int) ini_get( 'max_execution_time' ) );
		}

		ob_start();
		self::fetch_verifications();
		$ob_contents = ob_get_contents();
		if ( strlen( $ob_contents ) ) {
			Exception::sendCrashReport( $ob_contents );
		}
		ob_end_clean();

	}

	public static function fetch_verifications() {

		$api_key = get_option( EVH_PLUGIN_PREFIX . '_api_key', null );
		if ( $api_key === null ) {
			add_option( EVH_PLUGIN_PREFIX . '_api_key', '' );
			$api_key = '';
		}
		$api_error_message = get_option( EVH_PLUGIN_PREFIX . '_api_error_message', null );
		if ( $api_error_message === null ) {
			add_option( EVH_PLUGIN_PREFIX . '_api_error_message', '' );
		}
		$siteurl = get_option( 'siteurl', null );
		if ( ! strlen( $api_key ) || $siteurl === null ) {

			return false;
		}

		// storage dir
		$uniqid = get_option( EVH_PLUGIN_PREFIX . '_uniqid', null );
		if ( $uniqid === null ) {
			$uniqid = uniqid();
			add_option( EVH_PLUGIN_PREFIX . '_uniqid', $uniqid );
		}
		$wp_upload_dir   = wp_upload_dir();
		$file_upload_dir = $wp_upload_dir['basedir'] . '/email-verification-by-hardbouncecleaner/' . $uniqid;
		if ( ! file_exists( $file_upload_dir ) ) {

			return false;
		}

		// web service call
		$args    = array( 'language' => substr( get_locale(), 0, 2 ) );
		$opts    = array(
			"http" => array(
				"method" => "GET",
				"header" => "X-HardBounceCleaner-Key: $api_key\r\n"
			)
		);
		$context = stream_context_create( $opts );
		$api_url = 'https://www.hardbouncecleaner.com/api/v1/file/list?' . http_build_query( $args );
		$data    = file_get_contents( $api_url, false, $context );

		if ( $data === false ) {

			return false;
		}
		$json = json_decode( $data, true );

		if ( ! $json['success'] ) {
			update_option( EVH_PLUGIN_PREFIX . '_api_error_message', $json['error_message'] );
		}
	}

	/**
	 * @throws Exception
	 */
	public static function cron_daily() {

		if ( ! defined( 'EVH_PLUGIN_START_TIME' ) ) {
			define( 'EVH_PLUGIN_START_TIME', time() );
		}
		if ( ! defined( 'EVH_PLUGIN_MAX_EXECUTION_TIME' ) ) {
			$max_execution_time = ( 60 * 20 );
			ini_set( 'max_execution_time', $max_execution_time );
			set_time_limit( $max_execution_time );
			// get the max_execution_time value in case of safe mode
			define( 'EVH_PLUGIN_MAX_EXECUTION_TIME', (int) ini_get( 'max_execution_time' ) );
		}

		ob_start();
		self::install();
		self::detect();
		self::update();
		self::verify();
		self::write_file();
		$ob_contents = ob_get_contents();
		if ( strlen( $ob_contents ) ) {
			Exception::sendCrashReport( $ob_contents );
		}
		ob_end_clean();
	}

	/**
	 * @throws Exception
	 */
	public static function install() {
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		global $wpdb;

		$db_ver = get_option( EVH_PLUGIN_PREFIX . '_db_ver', null );
		if ( $db_ver === null ) {
			$db_ver = 0;
			add_option( EVH_PLUGIN_PREFIX . '_db_ver', $db_ver );
		}

		$files = scandir( EVH_PLUGIN_DIR . '/sql', SCANDIR_SORT_ASCENDING );
		foreach ( $files as $file ) {
			$match = array();
			if ( ! preg_match( '/([0-9]+)\.sql/', $file, $match ) ) {
				continue;
			}

			$file_ver = (int) $match[1];
			if ( $file_ver <= $db_ver ) {
				continue;
			}

			$sql = file_get_contents( EVH_PLUGIN_DIR . '/sql/' . $file );
			$sql = str_replace(
				array(
					'DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci',
					'email_verification_by_hardbouncecleaner_config ',
					'email_verification_by_hardbouncecleaner_list ',
					'email_verification_by_hardbouncecleaner_group ',
					'email_verification_by_hardbouncecleaner_list_has_group ',
					'email_verification_by_hardbouncecleaner_mx '
				),
				array(
					$wpdb->get_charset_collate(),
					$wpdb->prefix . "email_verification_by_hardbouncecleaner_config ",
					$wpdb->prefix . "email_verification_by_hardbouncecleaner_list ",
					$wpdb->prefix . "email_verification_by_hardbouncecleaner_group ",
					$wpdb->prefix . "email_verification_by_hardbouncecleaner_list_has_group ",
					$wpdb->prefix . "email_verification_by_hardbouncecleaner_mx ",
				),
				$sql );

			$query = $wpdb->query( $sql );
			if ( $query === false ) {
				throw new Exception( sprintf( __( "Database update version %d error" ), $file_ver ), 500, null, array( 'sql' => $sql ) );
			}

			update_option( EVH_PLUGIN_PREFIX . '_db_ver', $file_ver );
		}
	}

	/**
	 *
	 */
	public static function detect() {
		global $wpdb;

		$email_columns = array();

		$mytables = $wpdb->get_results( "SHOW TABLES" );
		foreach ( $mytables as $mytable ) {

			foreach ( $mytable as $t ) {

				$table_name_list = $wpdb->prefix . "email_verification_by_hardbouncecleaner_list";
				if ( $t === $table_name_list ) {
					continue;
				}

				$columns = $wpdb->get_results( "SHOW columns FROM $t" );
				foreach ( $columns as $column ) {

					if ( preg_match( '/email/', $column->Field ) && preg_match( '/char/', $column->Type ) ) {

						if ( ! isset( $email_columns[ $t ] ) ) {
							$email_columns[ $t ] = array();
						}
						$email_columns[ $t ][] = $column->Field;
					}
				}
			}
		}

		foreach ( $email_columns as $tablename => $columns ) {

			$table_name_config = $wpdb->prefix . "email_verification_by_hardbouncecleaner_config";
			$tablename         = str_replace( $wpdb->prefix, '', $tablename );

			foreach ( $columns as $columnname ) {

				$config_id = crc32( $tablename . '-' . $columnname );
				$wpdb->query( "INSERT IGNORE INTO $table_name_config (id, tablename, columnname)
						VALUES ($config_id, '" . esc_sql( $tablename ) . "', '" . esc_sql( $columnname ) . "');" );
			}
		}
	}

	/**
	 * @param null $limit
	 *
	 * @return bool
	 */
	public static function update( $limit = null ) {
		global $wpdb;

		$counter = 0;

		$table_name_config = $wpdb->prefix . "email_verification_by_hardbouncecleaner_config";
		$configs           = $wpdb->get_results( "SELECT * FROM $table_name_config" );
		foreach ( $configs as $config ) {

			$table = $wpdb->prefix . $config->tablename;
			$sql   = "SELECT " . $config->columnname . " FROM $table";
			if ( $limit !== null ) {
				$sql .= " LIMIT " . (int) $limit;
			}

			$emails = $wpdb->get_results( $sql, ARRAY_A );
			foreach ( $emails as $email ) {

				$current_email = trim( $email[ $config->columnname ] );
				$current_email = sanitize_email( $current_email );

				if ( is_email( $current_email ) ) {
					$table_name_list = $wpdb->prefix . "email_verification_by_hardbouncecleaner_list";
					$list_id         = crc32( $current_email );
					$wpdb->query( "INSERT IGNORE INTO $table_name_list (id, email, created_at, status )
						VALUES ($list_id, '" . esc_sql( $current_email ) . "', NOW(), 'pending');" );

					$table_name_group = $wpdb->prefix . "email_verification_by_hardbouncecleaner_group";
					$group_name       = ucwords( str_replace( '_', ' ', $config->tablename ) );
					$group_id         = crc32( $group_name );
					$wpdb->query( "INSERT IGNORE INTO $table_name_group (id, name )
						VALUES ($group_id, '" . esc_sql( $group_name ) . "' );" );

					$table_name_list_has_group = $wpdb->prefix . "email_verification_by_hardbouncecleaner_list_has_group";
					$wpdb->query( "INSERT IGNORE INTO $table_name_list_has_group (list_id, group_id )
						VALUES ($list_id, $group_id );" );

					$counter ++;
					if ( $limit !== null ) {
						if ( $counter >= $limit ) {
							return true;
						}
					}
				}
			}
		}
	}

	/**
	 *
	 */
	public static function verify() {
		global $wpdb;

		if ( defined( 'EVH_PLUGIN_START_TIME' ) ) {

			if ( time() > ( EVH_PLUGIN_START_TIME + ( EVH_PLUGIN_MAX_EXECUTION_TIME * .90 ) ) ) {
				return;
			}
		}

		$table_name_list = $wpdb->prefix . "email_verification_by_hardbouncecleaner_list";
		$lists           = $wpdb->get_results( "SELECT * FROM $table_name_list WHERE role IS NULL ", ARRAY_A );
		foreach ( $lists as $list ) {

			if ( defined( 'EVH_PLUGIN_START_TIME' ) ) {

				if ( time() > ( EVH_PLUGIN_START_TIME + ( EVH_PLUGIN_MAX_EXECUTION_TIME * .90 ) ) ) {
					break;
				}
			}

			$email = $list['email'];

			// role
			$role = self::is_role( $email );

			// disposable
			$disposable = self::is_disposable( $email );

			// mx
			$mx_error = self::is_mx_error( $email );

			// status
			$status = 'unknown';
			if ( $mx_error === true ) {
				$status = 'invalid';
			}

			$wpdb->update(
				$table_name_list,
				array( 'role' => (int) $role, 'disposable' => (int) $disposable, 'mx' => (int) $mx_error, 'status' => $status ),
				array( 'id' => $list['id'] )
			);
		}
	}

	/**
	 * @param $email
	 *
	 * @return bool
	 */
	public static function is_role( $email ) {
		$roles = array(
			'2015',
			'2016',
			'2017',
			'2018',
			'2019',
			'2020',
			'abuse',
			'academy',
			'accessibility',
			'account',
			'accountant',
			'accounting',
			'accountmanagers',
			'accounts',
			'accountspayable',
			'acquisition',
			'admin',
			'admin1',
			'administracao',
			'administracion',
			'administrador',
			'administratie',
			'administratif',
			'administration',
			'administrativo',
			'administrator',
			'administrators',
			'admins',
			'adminteam',
			'admissions',
			'adops',
			'ads',
			'adventure',
			'advertise',
			'advertising',
			'advertisingsales',
			'advice',
			'advisor',
			'advisors',
			'adwords',
			'affiliate',
			'affiliates',
			'agence',
			'agencia',
			'agency',
			'agents',
			'alarm',
			'alarms',
			'alert',
			'alerts',
			'alexa',
			'all',
			'all-employees',
			'all-pms',
			'all-staff',
			'all-team',
			'all-users',
			'all.employees',
			'all.staff',
			'all.users',
			'all_staff',
			'alla',
			'alle',
			'allemployees',
			'allhands',
			'allsales',
			'allstaff',
			'allstudents',
			'allteachers',
			'allteam',
			'allusers',
			'alpha',
			'alphas',
			'alumni',
			'ambassadors',
			'amministrazione',
			'analysts',
			'analytics',
			'android',
			'angels',
			'animation',
			'announce',
			'announcements',
			'ap',
			'api',
			'app',
			'apple',
			'application',
			'applications',
			'apply',
			'appointments',
			'apps',
			'archives',
			'asistente',
			'asset',
			'assistanthead',
			'assistencia',
			'assistenza',
			'associates',
			'associates-all',
			'ateam',
			'atencionalcliente',
			'atendimento',
			'auctions',
			'available',
			'backend',
			'backend-dev',
			'backup',
			'bd',
			'benefits',
			'berlin',
			'bestellung',
			'beta',
			'biblioteca',
			'bibliotheque',
			'billing',
			'bills',
			'biuro',
			'biz',
			'bizdev',
			'blog',
			'board',
			'bod',
			'bookclub',
			'booking',
			'bookings',
			'boston',
			'boxoffice',
			'brand',
			'branding',
			'brands',
			'brandsolutions',
			'broadcast',
			'buchhaltung',
			'bugs',
			'build',
			'bursar',
			'busdev',
			'business',
			'business_team',
			'businessdevelopment',
			'ca',
			'caltrain',
			'campaign',
			'campaigns',
			'campusteam',
			'capacitacion',
			'captain',
			'captains',
			'care',
			'career',
			'careers',
			'catering',
			'central',
			'centro',
			'ceo',
			'ceos',
			'channel-sales',
			'chat',
			'chatter',
			'chef',
			'chicago',
			'china',
			'citymanagers',
			'classof2016',
			'classof2017',
			'classof2018',
			'classof2019',
			'classroom_teachers',
			'client',
			'clientes',
			'clients',
			'clientservices',
			'clinic',
			'cloud',
			'cm',
			'co-op',
			'coach',
			'coaches',
			'coaching',
			'code',
			'colaboradores',
			'colegio',
			'com',
			'comenzi',
			'comercial',
			'comercial1',
			'comercial2',
			'comments',
			'commercial',
			'commerciale',
			'commissions',
			'committee',
			'comms',
			'communication',
			'communications',
			'community',
			'community',
			'company',
			'company.wide',
			'compete',
			'competition',
			'compliance',
			'compras',
			'compta',
			'comptabilite',
			'comunicacao',
			'comunicacion',
			'comunicaciones',
			'comunicazione',
			'concierge',
			'conference',
			'connect',
			'consultant',
			'consultas',
			'consulting',
			'consultoria',
			'contabil',
			'contabilidad',
			'contabilidade',
			'contabilita',
			'contact',
			'contactenos',
			'contacto',
			'contactus',
			'contador',
			'contato',
			'content',
			'contractor',
			'contractors',
			'contracts',
			'controller',
			'coordinator',
			'copyright',
			'core',
			'coreteam',
			'corp',
			'corporate',
			'corporatesales',
			'council',
			'courrier',
			'creative',
			'crew',
			'crm',
			'cs',
			'csm',
			'csteam',
			'cultura',
			'culture',
			'customer',
			'customer.service',
			'customercare',
			'customerfeedback',
			'customers',
			'customerservice',
			'customerservicecenter',
			'customerservices',
			'customersuccess',
			'customersupport',
			'custserv',
			'daemon',
			'data',
			'database',
			'deals',
			'dean',
			'delivery',
			'demo',
			'denver',
			'departures',
			'deploy',
			'deputy',
			'deputyhead',
			'design',
			'designer',
			'designers',
			'dev',
			'developer',
			'developers',
			'development',
			'devnull',
			'devops',
			'devs',
			'devteam',
			'digital',
			'digsitesvalue',
			'direccion',
			'direction',
			'director',
			'directors',
			'directory',
			'diretoria',
			'direzione',
			'discuss',
			'dispatch',
			'diversity',
			'dns',
			'docs',
			'domain',
			'domainmanagement',
			'domains',
			'donations',
			'donors',
			'download',
			'dreamteam',
			'ecommerce',
			'editor',
			'editorial',
			'editors',
			'education',
			'einkauf',
			'email',
			'emergency',
			'employee',
			'employees',
			'employment',
			'eng',
			'eng-all',
			'engagement',
			'engineering',
			'engineers',
			'english',
			'enq',
			'enquire',
			'enquires',
			'enquiries',
			'enquiry',
			'enrollment',
			'enterprise',
			'equipe',
			'equipo',
			'error',
			'errors',
			'escritorio',
			'europe',
			'event',
			'events',
			'everybody',
			'everyone',
			'exec',
			'execs',
			'execteam',
			'executive',
			'executives',
			'expenses',
			'expert',
			'experts',
			'export',
			'facilities',
			'facturacion',
			'faculty',
			'family',
			'farmacia',
			'faturamento',
			'fax',
			'fbl',
			'feedback',
			'fellows',
			'finance',
			'financeiro',
			'financeiro2',
			'finanzas',
			'firmapost',
			'fiscal',
			'food',
			'football',
			'founders',
			'france',
			'franchise',
			'friends',
			'frontdesk',
			'frontend',
			'frontoffice',
			'fte',
			'ftp',
			'fulltime',
			'fun',
			'fundraising',
			'gardner',
			'geeks',
			'general',
			'geral',
			'giving',
			'global',
			'grants',
			'graphics',
			'group',
			'growth',
			'hackathon',
			'hackers',
			'head',
			'head.office',
			'headoffice',
			'heads',
			'headteacher',
			'hello',
			'help',
			'helpdesk',
			'hi',
			'highschool',
			'hiring',
			'hola',
			'home',
			'homes',
			'hosting',
			'hostmaster',
			'hotel',
			'house',
			'hq',
			'hr',
			'hrdept',
			'hsstaff',
			'hsteachers',
			'humanresources',
			'ideas',
			'implementation',
			'import',
			'inbound',
			'inbox',
			'india',
			'info',
			'infor',
			'informacion',
			'informatica',
			'information',
			'informatique',
			'informativo',
			'infra',
			'infrastructure',
			'ingenieria',
			'innovation',
			'inoc',
			'inquiries',
			'inquiry',
			'insidesales',
			'insights',
			'instagram',
			'insurance',
			'integration',
			'integrations',
			'intern',
			'internal',
			'international',
			'internet',
			'interns',
			'internship',
			'invest',
			'investment',
			'investor',
			'investorrelations',
			'investors',
			'invoice',
			'invoices',
			'invoicing',
			'ios',
			'iphone',
			'ir',
			'ispfeedback',
			'ispsupport',
			'it',
			'ithelp',
			'itsupport',
			'itunes',
			'jira',
			'job',
			'jobs',
			'join',
			'jornalismo',
			'junk',
			'kontakt',
			'kundeservice',
			'la',
			'lab',
			'laboratorio',
			'labs',
			'ladies',
			'latam',
			'launch',
			'lead',
			'leaders',
			'leadership',
			'leadership-team',
			'leadershipteam',
			'leads',
			'leasing',
			'legal',
			'letters',
			'library',
			'licensing',
			'links',
			'list',
			'list-request',
			'login',
			'logistica',
			'logistics',
			'logistiek',
			'lt',
			'lunch',
			'mail',
			'mailbox',
			'maildaemon',
			'mailer-daemon',
			'mailerdaemon',
			'mailing',
			'maintenance',
			'management',
			'management-group',
			'management.team',
			'management_team',
			'manager',
			'managers',
			'marketing',
			'marketing-ops',
			'marketing-team',
			'marketingteam',
			'marketplace',
			'master',
			'mayor',
			'md',
			'media',
			'meetup',
			'member',
			'members',
			'membership',
			'mentors',
			'metrics',
			'mgmt',
			'middleschool',
			'misc',
			'mkt',
			'mktg',
			'mobile',
			'monitor',
			'monitoring',
			'montreal',
			'msstaff',
			'msteachers',
			'mt',
			'music',
			'network',
			'newbiz',
			'newbusiness',
			'news',
			'newsletter',
			'newyork',
			'nntp',
			'no-reply',
			'no.replay',
			'no.reply',
			'nobody',
			'noc',
			'none',
			'noreply',
			'noresponse',
			'northamerica',
			'nospam',
			'notes',
			'notifications',
			'notify',
			'nps',
			'null',
			'ny',
			'nyc',
			'nyoffice',
			'offboarding',
			'offers',
			'office',
			'officeadmin',
			'officemanager',
			'officers',
			'officestaff',
			'offtopic',
			'oficina',
			'onboarding',
			'online',
			'onsite',
			'ooo',
			'operaciones',
			'operations',
			'ops',
			'order',
			'orders',
			'ordini',
			'outage',
			'outreach',
			'owners',
			'parents',
			'paris',
			'partner',
			'partners',
			'partnerships',
			'parts',
			'pay',
			'payment',
			'payments',
			'paypal',
			'payroll',
			'pd',
			'people',
			'peoplemanagers',
			'peopleops',
			'performance',
			'personnel',
			'phish',
			'phishing',
			'photos',
			'planning',
			'platform',
			'pm',
			'portfolio',
			'post',
			'postbox',
			'postfix',
			'postmaster',
			'ppc',
			'pr',
			'prefeitura',
			'presales',
			'presidencia',
			'president',
			'presidente',
			'press',
			'presse',
			'prime',
			'principal',
			'principals',
			'privacy',
			'procurement',
			'prod',
			'produccion',
			'product',
			'product-team',
			'product.growth',
			'product.management',
			'product.managers',
			'product.team',
			'production',
			'productmanagers',
			'products',
			'productteam',
			'produto',
			'program',
			'programs',
			'project',
			'projectmanagers',
			'projects',
			'promo',
			'promotions',
			'protocollo',
			'proveedores',
			'publicidade',
			'publisher',
			'publishers',
			'purchase',
			'purchases',
			'purchasing',
			'qa',
			'qualidade',
			'questions',
			'quotes',
			'random',
			'realestate',
			'receipts',
			'recepcion',
			'reception',
			'receptionist',
			'recruit',
			'recruiter',
			'recruiters',
			'recruiting',
			'recruitment',
			'recrutement',
			'recursoshumanos',
			'redacao',
			'redaccion',
			'redaction',
			'redazione',
			'referrals',
			'register',
			'registrar',
			'registration',
			'relacionamento',
			'release',
			'releases',
			'remote',
			'remove',
			'rentals',
			'report',
			'reporting',
			'reports',
			'request',
			'requests',
			'research',
			'reservaciones',
			'reservas',
			'reservation',
			'reservations',
			'residents',
			'response',
			'restaurant',
			'resume',
			'resumes',
			'retail',
			'returns',
			'revenue',
			'rezervari',
			'rfp',
			'rnd',
			'rockstars',
			'root',
			'rrhh',
			'rsvp',
			'sales',
			'sales-team',
			'sales.team',
			'sales1',
			'sales2',
			'salesengineers',
			'salesforce',
			'salesops',
			'salesteam',
			'sanfrancisco',
			'school',
			'schooloffice',
			'science',
			'sdr',
			'se',
			'search',
			'seattle',
			'secretaria',
			'secretariaat',
			'secretaris',
			'secretary',
			'security',
			'sekretariat',
			'sem',
			'seniors',
			'seo',
			'server',
			'service',
			'serviceclient',
			'servicedesk',
			'services',
			'servicioalcliente',
			'sf',
			'sf-office',
			'sfo',
			'sfoffice',
			'sfteam',
			'shareholders',
			'shipping',
			'shop',
			'shopify',
			'shopping',
			'signup',
			'signups',
			'singapore',
			'sistemas',
			'site',
			'smtp',
			'social',
			'socialclub',
			'socialmedia',
			'socios',
			'software',
			'solutions',
			'soporte',
			'sos',
			'spam',
			'sponsorship',
			'sport',
			'squad',
			'staff',
			'startups',
			'stats',
			'stockholm',
			'store',
			'stories',
			'strategy',
			'stripe',
			'student',
			'students',
			'studio',
			'submissions',
			'submit',
			'subscribe',
			'subscriptions',
			'success',
			'suggestions',
			'supervisor',
			'supervisors',
			'suporte',
			'supply',
			'support',
			'support-team',
			'supportteam',
			'suprimentos',
			'sydney',
			'sysadmin',
			'system',
			'systems',
			'ta',
			'talent',
			'tax',
			'teachers',
			'team',
			'teamleaders',
			'teamleads',
			'tech',
			'technical',
			'technik',
			'technology',
			'techops',
			'techsupport',
			'techteam',
			'tecnologia',
			'tesoreria',
			'test',
			'testgroup',
			'testing',
			'the.principal',
			'theoffice',
			'theteam',
			'tickets',
			'time',
			'timesheets',
			'todos',
			'tools',
			'tour',
			'trade',
			'trainers',
			'training',
			'transport',
			'travel',
			'treasurer',
			'tribe',
			'trustees',
			'turismo',
			'twitter',
			'uk',
			'undisclosed-recipients',
			'unsubscribe',
			'update',
			'updates',
			'us',
			'usa',
			'usenet',
			'user',
			'users',
			'usteam',
			'uucp',
			'ux',
			'vendas',
			'vendas1',
			'vendas2',
			'vendor',
			'vendors',
			'ventas',
			'ventas1',
			'ventas2',
			'verkauf',
			'verwaltung',
			'video',
			'vip',
			'voicemail',
			'volunteer',
			'volunteering',
			'volunteers',
			'vorstand',
			'warehouse',
			'watercooler',
			'web',
			'webadmin',
			'webdesign',
			'webdev',
			'webinars',
			'webmaster',
			'website',
			'webteam',
			'welcome',
			'whois',
			'wholesale',
			'women',
			'wordpress',
			'work',
			'workshop',
			'writers',
			'www',
			'zentrale'
		);

		$is_role = false;
		foreach ( $roles as $role ) {
			if ( preg_match( '/^' . quotemeta( $role ) . '@/', $email ) ) {
				$is_role = true;
				break;
			}
		}

		return $is_role;
	}

	/**
	 * @param $email
	 *
	 * @return bool
	 */
	public static function is_disposable( $email ) {

		$disposables = array(
			'0-mail.com',
			'0815.ru',
			'0clickemail.com',
			'0wnd.net',
			'0wnd.org',
			'10minutemail.com',
			'20minutemail.com',
			'2prong.com',
			'30minutemail.com',
			'3d-painting.com',
			'4warding.com',
			'4warding.net',
			'4warding.org',
			'60minutemail.com',
			'675hosting.com',
			'675hosting.net',
			'675hosting.org',
			'6url.com',
			'75hosting.com',
			'75hosting.net',
			'75hosting.org',
			'7tags.com',
			'9ox.net',
			'a-bc.net',
			'afrobacon.com',
			'ajaxapp.net',
			'amilegit.com',
			'amiri.net',
			'amiriindustries.com',
			'anonbox.net',
			'anonymbox.com',
			'antichef.com',
			'antichef.net',
			'antispam.de',
			'baxomale.ht.cx',
			'beefmilk.com',
			'binkmail.com',
			'bio-muesli.net',
			'bobmail.info',
			'bodhi.lawlita.com',
			'bofthew.com',
			'brefmail.com',
			'broadbandninja.com',
			'bsnow.net',
			'bugmenot.com',
			'bumpymail.com',
			'casualdx.com',
			'centermail.com',
			'centermail.net',
			'chogmail.com',
			'choicemail1.com',
			'cool.fr.nf',
			'correo.blogos.net',
			'cosmorph.com',
			'courriel.fr.nf',
			'courrieltemporaire.com',
			'cubiclink.com',
			'curryworld.de',
			'cust.in',
			'dacoolest.com',
			'dandikmail.com',
			'dayrep.com',
			'deadaddress.com',
			'deadspam.com',
			'despam.it',
			'despammed.com',
			'devnullmail.com',
			'dfgh.net',
			'digitalsanctuary.com',
			'discardmail.com',
			'discardmail.de',
			'emailmiser.com',
			'disposableaddress.com',
			'disposeamail.com',
			'disposemail.com',
			'dispostable.com',
			'dm.w3internet.co.ukexample.com',
			'dodgeit.com',
			'dodgit.com',
			'dodgit.org',
			'donemail.ru',
			'dontreg.com',
			'dontsendmespam.de',
			'dump-email.info',
			'dumpandjunk.com',
			'dumpmail.de',
			'dumpyemail.com',
			'e4ward.com',
			'email60.com',
			'emaildienst.de',
			'emailias.com',
			'emailigo.de',
			'emailinfive.com',
			'emailmiser.com',
			'emailsensei.com',
			'emailtemporario.com.br',
			'emailto.de',
			'emailwarden.com',
			'emailx.at.hm',
			'emailxfer.com',
			'emz.net',
			'enterto.com',
			'ephemail.net',
			'etranquil.com',
			'etranquil.net',
			'etranquil.org',
			'explodemail.com',
			'fakeinbox.com',
			'fakeinformation.com',
			'fastacura.com',
			'fastchevy.com',
			'fastchrysler.com',
			'fastkawasaki.com',
			'fastmazda.com',
			'fastmitsubishi.com',
			'fastnissan.com',
			'fastsubaru.com',
			'fastsuzuki.com',
			'fasttoyota.com',
			'fastyamaha.com',
			'filzmail.com',
			'fizmail.com',
			'fr33mail.info',
			'frapmail.com',
			'front14.org',
			'fux0ringduh.com',
			'garliclife.com',
			'get1mail.com',
			'get2mail.fr',
			'getonemail.com',
			'getonemail.net',
			'ghosttexter.de',
			'girlsundertheinfluence.com',
			'gishpuppy.com',
			'gowikibooks.com',
			'gowikicampus.com',
			'gowikicars.com',
			'gowikifilms.com',
			'gowikigames.com',
			'gowikimusic.com',
			'gowikinetwork.com',
			'gowikitravel.com',
			'gowikitv.com',
			'great-host.in',
			'greensloth.com',
			'gsrv.co.uk',
			'guerillamail.biz',
			'guerillamail.com',
			'guerillamail.net',
			'guerillamail.org',
			'guerrillamail.biz',
			'guerrillamail.com',
			'guerrillamail.de',
			'guerrillamail.net',
			'guerrillamail.org',
			'guerrillamailblock.com',
			'h.mintemail.com',
			'h8s.org',
			'haltospam.com',
			'hatespam.org',
			'hidemail.de',
			'hochsitze.com',
			'hotpop.com',
			'hulapla.de',
			'ieatspam.eu',
			'ieatspam.info',
			'ihateyoualot.info',
			'iheartspam.org',
			'imails.info',
			'inboxclean.com',
			'inboxclean.org',
			'incognitomail.com',
			'incognitomail.net',
			'incognitomail.org',
			'insorg-mail.info',
			'ipoo.org',
			'irish2me.com',
			'iwi.net',
			'jetable.com',
			'jetable.fr.nf',
			'jetable.net',
			'jetable.org',
			'jnxjn.com',
			'junk1e.com',
			'kasmail.com',
			'kaspop.com',
			'keepmymail.com',
			'killmail.com',
			'killmail.net',
			'kir.ch.tc',
			'klassmaster.com',
			'klassmaster.net',
			'klzlk.com',
			'kulturbetrieb.info',
			'kurzepost.de',
			'letthemeatspam.com',
			'lhsdv.com',
			'lifebyfood.com',
			'link2mail.net',
			'litedrop.com',
			'lol.ovpn.to',
			'lookugly.com',
			'lopl.co.cc',
			'lortemail.dk',
			'lr78.com',
			'm4ilweb.info',
			'maboard.com',
			'mail-temporaire.fr',
			'mail.by',
			'mail.mezimages.net',
			'mail2rss.org',
			'mail333.com',
			'mail4trash.com',
			'mailbidon.com',
			'mailblocks.com',
			'mailcatch.com',
			'maileater.com',
			'mailexpire.com',
			'mailfreeonline.com',
			'mailin8r.com',
			'mailinater.com',
			'mailinator.com',
			'mailinator.net',
			'mailinator2.com',
			'mailincubator.com',
			'mailme.ir',
			'mailme.lv',
			'mailmetrash.com',
			'mailmoat.com',
			'mailnator.com',
			'mailnesia.com',
			'mailnull.com',
			'mailshell.com',
			'mailsiphon.com',
			'mailslite.com',
			'mailzilla.com',
			'mailzilla.org',
			'mbx.cc',
			'mega.zik.dj',
			'meinspamschutz.de',
			'meltmail.com',
			'messagebeamer.de',
			'mierdamail.com',
			'mintemail.com',
			'moburl.com',
			'moncourrier.fr.nf',
			'monemail.fr.nf',
			'monmail.fr.nf',
			'msa.minsmail.com',
			'mt2009.com',
			'mx0.wwwnew.eu',
			'mycleaninbox.net',
			'mypartyclip.de',
			'myphantomemail.com',
			'myspaceinc.com',
			'myspaceinc.net',
			'myspaceinc.org',
			'myspacepimpedup.com',
			'myspamless.com',
			'mytrashmail.com',
			'neomailbox.com',
			'nepwk.com',
			'nervmich.net',
			'nervtmich.net',
			'netmails.com',
			'netmails.net',
			'netzidiot.de',
			'neverbox.com',
			'no-spam.ws',
			'nobulk.com',
			'noclickemail.com',
			'nogmailspam.info',
			'nomail.xl.cx',
			'nomail2me.com',
			'nomorespamemails.com',
			'nospam.ze.tc',
			'nospam4.us',
			'nospamfor.us',
			'nospamthanks.info',
			'notmailinator.com',
			'nowmymail.com',
			'nurfuerspam.de',
			'nus.edu.sg',
			'nwldx.com',
			'objectmail.com',
			'obobbo.com',
			'oneoffemail.com',
			'onewaymail.com',
			'online.ms',
			'oopi.org',
			'ordinaryamerican.net',
			'otherinbox.com',
			'ourklips.com',
			'outlawspam.com',
			'ovpn.to',
			'owlpic.com',
			'pancakemail.com',
			'pimpedupmyspace.com',
			'pjjkp.com',
			'politikerclub.de',
			'poofy.org',
			'pookmail.com',
			'privacy.net',
			'proxymail.eu',
			'prtnx.com',
			'punkass.com',
			'PutThisInYourSpamDatabase.com',
			'qq.com',
			'quickinbox.com',
			'rcpt.at',
			'recode.me',
			'recursor.net',
			'regbypass.com',
			'regbypass.comsafe-mail.net',
			'rejectmail.com',
			'rklips.com',
			'rmqkr.net',
			'rppkn.com',
			'rtrtr.com',
			's0ny.net',
			'safe-mail.net',
			'safersignup.de',
			'safetymail.info',
			'safetypost.de',
			'sandelf.de',
			'saynotospams.com',
			'selfdestructingmail.com',
			'SendSpamHere.com',
			'sharklasers.com',
			'shiftmail.com',
			'shitmail.me',
			'shortmail.net',
			'sibmail.com',
			'skeefmail.com',
			'slaskpost.se',
			'slopsbox.com',
			'smellfear.com',
			'snakemail.com',
			'sneakemail.com',
			'sofimail.com',
			'sofort-mail.de',
			'sogetthis.com',
			'soodonims.com',
			'spam.la',
			'spam.su',
			'spamavert.com',
			'spambob.com',
			'spambob.net',
			'spambob.org',
			'spambog.com',
			'spambog.de',
			'spambog.ru',
			'spambox.info',
			'spambox.irishspringrealty.com',
			'spambox.us',
			'spamcannon.com',
			'spamcannon.net',
			'spamcero.com',
			'spamcon.org',
			'spamcorptastic.com',
			'spamcowboy.com',
			'spamcowboy.net',
			'spamcowboy.org',
			'spamday.com',
			'spamex.com',
			'spamfree24.com',
			'spamfree24.de',
			'spamfree24.eu',
			'spamfree24.info',
			'spamfree24.net',
			'spamfree24.org',
			'spamgourmet.com',
			'spamgourmet.net',
			'spamgourmet.org',
			'SpamHereLots.com',
			'SpamHerePlease.com',
			'spamhole.com',
			'spamify.com',
			'spaminator.de',
			'spamkill.info',
			'spaml.com',
			'spaml.de',
			'spammotel.com',
			'spamobox.com',
			'spamoff.de',
			'spamslicer.com',
			'spamspot.com',
			'spamthis.co.uk',
			'spamthisplease.com',
			'spamtrail.com',
			'speed.1s.fr',
			'supergreatmail.com',
			'supermailer.jp',
			'suremail.info',
			'teewars.org',
			'teleworm.com',
			'tempalias.com',
			'tempe-mail.com',
			'tempemail.biz',
			'tempemail.com',
			'TempEMail.net',
			'tempinbox.co.uk',
			'tempinbox.com',
			'tempmail.it',
			'tempmail2.com',
			'tempomail.fr',
			'temporarily.de',
			'temporarioemail.com.br',
			'temporaryemail.net',
			'temporaryforwarding.com',
			'temporaryinbox.com',
			'thanksnospam.info',
			'thankyou2010.com',
			'thisisnotmyrealemail.com',
			'throwawayemailaddress.com',
			'tilien.com',
			'tmailinator.com',
			'tradermail.info',
			'trash-amil.com',
			'trash-mail.at',
			'trash-mail.com',
			'trash-mail.de',
			'trash2009.com',
			'trashemail.de',
			'trashmail.at',
			'trashmail.com',
			'trashmail.de',
			'trashmail.me',
			'trashmail.net',
			'trashmail.org',
			'trashmail.ws',
			'trashmailer.com',
			'trashymail.com',
			'trashymail.net',
			'trillianpro.com',
			'turual.com',
			'twinmail.de',
			'tyldd.com',
			'uggsrock.com',
			'upliftnow.com',
			'uplipht.com',
			'venompen.com',
			'veryrealemail.com',
			'viditag.com',
			'viewcastmedia.com',
			'viewcastmedia.net',
			'viewcastmedia.org',
			'webm4il.info',
			'wegwerfadresse.de',
			'wegwerfemail.de',
			'wegwerfmail.de',
			'wegwerfmail.net',
			'wegwerfmail.org',
			'wetrainbayarea.com',
			'wetrainbayarea.org',
			'wh4f.org',
			'whyspam.me',
			'willselfdestruct.com',
			'winemaven.info',
			'wronghead.com',
			'wuzup.net',
			'wuzupmail.net',
			'www.e4ward.com',
			'www.gishpuppy.com',
			'www.mailinator.com',
			'wwwnew.eu',
			'xagloo.com',
			'xemaps.com',
			'xents.com',
			'xmaily.com',
			'xoxy.net',
			'yep.it',
			'yogamaven.com',
			'yopmail.com',
			'yopmail.fr',
			'yopmail.net',
			'ypmail.webarnak.fr.eu.org',
			'yuurok.com',
			'zehnminutenmail.de',
			'zippymail.info',
			'zoaxe.com',
			'zoemail.org',
			'33mail.com',
			'maildrop.cc',
			'inboxalias.com',
			'spam4.me',
			'koszmail.pl',
			'tagyourself.com',
			'whatpaas.com',
			'drdrb.com',
			'emeil.in',
			'azmeil.tk',
			'mailfa.tk',
			'inbax.tk',
			'emeil.ir',
			'trbvm.com',
			'10minut.com.pl',
			'maildrop.cc',
			'boximail.com',
			'oalsp.com',
			'binka.me',
			'doanart.com',
			'p33.org',
			'bestvpn.top',
			'10vpn.info',
			'mailgov.info',
			'janproz.com',
			'pcmylife.com',
			'vpstraffic.com',
			'garage46.com',
			'buy003.com',
			'uscaves.com',
			'vektik.com',
			'amail.club',
			'cmail.club',
			'wmail.club',
			'banit.me',
			'nada.ltd',
			'duck2.club',
			'cars2.club',
			'nada.email',
			'sharklasers.com',
			'guerrillamail.info',
			'grr.la',
			'guerrillamail.biz',
			'guerrillamail.com',
			'guerrillamail.de',
			'guerrillamail.net',
			'guerrillamail.org',
			'guerrillamailblock.com',
			'pokemail.net',
			'spam4.me',
			'ibsats.com',
			'qiq.us',
			'u.0u.ro',
			'vlwomhm.xyz',
			'dropmail.me',
			'10mail.org',
			'yomail.info',
			'emltmp.com',
			'emlpro.com',
			'emlhub.com',
		);

		$is_disposable = false;
		foreach ( $disposables as $disposable ) {
			if ( preg_match( '/@' . quotemeta( $disposable ) . '$/', $email ) ) {
				$is_disposable = true;
				break;
			}
		}

		return $is_disposable;
	}

	/**
	 * @param $email
	 *
	 * @return bool
	 */
	public static function is_mx_error( $email ) {
		global $wpdb;

		$split = explode( '@', $email );
		if ( ! isset( $split[1] ) ) {
			return false;
		}

		$domain = $split[1];

		$mx_id         = crc32( $domain );
		$table_name_mx = $wpdb->prefix . "email_verification_by_hardbouncecleaner_mx";
		$sql           = "SELECT error FROM $table_name_mx WHERE id = $mx_id ";
		$result        = $wpdb->get_row( $sql, 'ARRAY_A' );

		if ( isset( $result['error'] ) ) {
			return (bool) $result['error'];
		}

		$checkdnsrr = @checkdnsrr( $domain, "MX" );

		$error = false;
		if ( $checkdnsrr === false ) {
			$error = true;
		}

		$sql_insert = "INSERT IGNORE INTO $table_name_mx (id, domain, error) VALUES ($mx_id, '" . esc_sql( $domain ) . "', " . (int) $error . ")";
		$wpdb->query( $sql_insert );

		return $error;
	}

	/**
	 * @throws Exception
	 */
	public static function write_file() {
		global $wpdb;

		if ( defined( 'EVH_PLUGIN_START_TIME' ) ) {

			if ( time() > ( EVH_PLUGIN_START_TIME + ( EVH_PLUGIN_MAX_EXECUTION_TIME * .90 ) ) ) {
				return;
			}
		}

		// table list
		$table_name_list           = $wpdb->prefix . "email_verification_by_hardbouncecleaner_list";
		$table_name_list_has_group = $wpdb->prefix . "email_verification_by_hardbouncecleaner_list_has_group";
		$table_name_group          = $wpdb->prefix . "email_verification_by_hardbouncecleaner_group";

		// create a random file to store the files
		$uniqid = get_option( EVH_PLUGIN_PREFIX . '_uniqid', null );
		if ( $uniqid === null ) {
			$uniqid = uniqid();
			add_option( EVH_PLUGIN_PREFIX . '_uniqid', $uniqid );
		}

		// dir creation
		$wp_upload_dir = wp_upload_dir();
		foreach (
			array(
				$wp_upload_dir['basedir'] . '/email-verification-by-hardbouncecleaner',
				$wp_upload_dir['basedir'] . '/email-verification-by-hardbouncecleaner/' . $uniqid
			) as $dir
		) {
			if ( file_exists( $dir ) ) {
				continue;
			}
			if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
				throw new Exception( __( "Unable to create directory" ), 0, null, array( 'file_upload_dir' => $dir ) );
			}
			file_put_contents( $dir . '/index.php', '' );
		}
		$file_upload_dir = $wp_upload_dir['basedir'] . '/email-verification-by-hardbouncecleaner/' . $uniqid;


		// file groups
		$files  = array( 'all' => 'WHERE 1=1' );
		$groups = $wpdb->get_results( "SELECT * FROM $table_name_group", ARRAY_A );
		foreach ( $groups as $group ) {

			$filename           = strtolower( str_replace( ' ', '-', $group['name'] ) );
			$files[ $filename ] = "JOIN $table_name_list_has_group lh ON lh.list_id = l.id WHERE lh.group_id = " . $group['id'];
		}

		$loop_break = false;
		foreach ( $files as $filename => $condition ) {

			if ( $loop_break ) {
				break;
			}

			foreach ( array( 'valid', 'unknown', 'invalid', 'toverify' ) as $status ) {

				if ( $loop_break ) {
					break;
				}

				$header        = array(
					'l.email'                          => 'email',
					'GROUP_CONCAT(g.name) AS origin'   => 'origin',
					'DATE(l.created_at) AS created_at' => 'created_at',
					'l.role'                           => 'role',
					'l.disposable'                     => 'disposable',
					'l.mx'                             => 'mx',
					"IFNULL(l.risky, '?') as risky"    => 'risky',
					'l.status'                         => 'status'
				);
				$sql_condition = $condition . " AND l.status = '$status' ";

				$file_path = $file_upload_dir . '/' . $filename . '-' . $status . '.csv';

				$insert_header = true;
				if ( file_exists( $file_path ) ) { // append
					$sql_condition .= " AND l.pending = 1 ";
					$insert_header = false;
				}

				// emails to send to hardbouncecleaner
				if ( $status === 'toverify' ) {
					$sql_condition = $condition . " AND l.checked = 0 AND l.status = 'unknown' ";
				}


				$sql   = "SELECT " . implode( ',', array_keys( $header ) ) . "
												FROM $table_name_list l
												JOIN $table_name_list_has_group lg ON l.id = lg.list_id
												JOIN $table_name_group g ON lg.group_id = g.id
												$sql_condition
												GROUP BY l.id";
				$lists = $wpdb->get_results( $sql, ARRAY_A );

				// list empty no need to create a file
				if ( ! count( $lists ) ) {
					continue;
				}

				// file append mode
				$fp = fopen( $file_path, 'a' );

				// header insertion
				if ( $insert_header ) {
					fputcsv( $fp, $header );
				}

				// data insertion
				foreach ( $lists as $list ) {

					if ( defined( 'EVH_PLUGIN_START_TIME' ) ) {

						if ( time() > ( EVH_PLUGIN_START_TIME + ( EVH_PLUGIN_MAX_EXECUTION_TIME * .90 ) ) ) {
							$loop_break = true;
							break;
						}
					}

					fputcsv( $fp, $list );
					$wpdb->query( "UPDATE $table_name_list SET pending = 0 WHERE email = '" . esc_sql( $list['email'] ) . "' " );
					if ( $status === 'toverify' ) {
						$wpdb->query( "UPDATE $table_name_list SET checked = 1 WHERE email = '" . esc_sql( $list['email'] ) . "' " );
					}
				}

				// close file
				fclose( $fp );

				if ( $status !== 'toverify' ) {
					continue;
				}

				// Full verification
				$api_key = get_option( EVH_PLUGIN_PREFIX . '_api_key', null );
				if ( $api_key === null ) {
					add_option( EVH_PLUGIN_PREFIX . '_api_key', '' );
					$api_key = '';
				}
				$api_error_message = get_option( EVH_PLUGIN_PREFIX . '_api_error_message', null );
				if ( $api_error_message === null ) {
					add_option( EVH_PLUGIN_PREFIX . '_api_error_message', '' );
				}
				$siteurl = get_option( 'siteurl', null );
				if ( ! strlen( $api_key ) || $siteurl === null || $filename === 'all' ) {
					continue;
				}
				$sitename = str_replace( array( '/', 'http:', 'https:' ), '', $siteurl );

				// send file to hardbouncleaner if activate
				$url = $siteurl . "/wp-content/uploads/email-verification-by-hardbouncecleaner/" . $uniqid . "/" . $filename . "-toverify.csv";

				$args    = array(
					'name'     => $sitename . ' ' . $filename . ' ' . date( 'd M Y' ),
					'url'      => $url,
					'language' => substr( get_locale(), 0, 2 )
				);
				$opts    = array(
					"http" => array(
						"method" => "GET",
						"header" => "X-HardBounceCleaner-Key: $api_key\r\n"
					)
				);
				$context = stream_context_create( $opts );
				$api_url = 'https://www.hardbouncecleaner.com/api/v1/file?' . http_build_query( $args );
				$data    = @file_get_contents( $api_url, false, $context );
				if ( $data === false ) {
					continue;
				}
				$json = json_decode( $data, true );
				if ( ! $json['success'] ) {
					update_option( EVH_PLUGIN_PREFIX . '_api_error_message', $json['error_message'] );
				}
			}
		}
	}

	/**
	 * @throws Exception
	 */
	public static function plugin_activation() {

		ob_start();
		self::install();
		self::detect();
		self::update( 100 );
		$ob_contents = ob_get_contents();
		if ( strlen( $ob_contents ) ) {
			Exception::sendCrashReport( $ob_contents );
		}
		ob_end_clean();
	}

	/**
	 *
	 */
	public static function plugin_deactivation() {
		global $wpdb;

		ob_start();
		$table_name_config         = $wpdb->prefix . "email_verification_by_hardbouncecleaner_config";
		$table_name_list_has_group = $wpdb->prefix . "email_verification_by_hardbouncecleaner_list_has_group";
		$table_name_list           = $wpdb->prefix . "email_verification_by_hardbouncecleaner_list";
		$table_name_group          = $wpdb->prefix . "email_verification_by_hardbouncecleaner_group";
		$table_name_mx             = $wpdb->prefix . "email_verification_by_hardbouncecleaner_mx";

		foreach ( array( $table_name_config, $table_name_list_has_group, $table_name_list, $table_name_group, $table_name_mx ) as $table ) {
			$sql = "DELETE FROM $table ";
			$wpdb->query( $sql );
		}
		$ob_contents = ob_get_contents();
		if ( strlen( $ob_contents ) ) {
			Exception::sendCrashReport( $ob_contents );
		}
		ob_end_clean();
	}
}