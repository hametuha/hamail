/*!
 * Marketing Email helper.
 *
 * @deps wp-element, wp-api-fetch, wp-i18n, wp-components, wp-data
 */

const { apiFetch, data } = wp;
const { Component, render, createRoot } = wp.element;
const { Button, Spinner } = wp.components;
const { __ } = wp.i18n;

class MarketingStatus extends Component {
	constructor( props ) {
		super( props );
		this.state = {
			loading: true,
			marketing: null,
		};
		data.subscribe( () => {
			// TODO: Update syncing after post updated.
			// 	data.dispatch( 'core/notices' ).createNotice( 'error', 'Over 140 letter, too long!', {
			// 		type: 'snackbar'
			// 	} ).then( ( res ) => {
			// 		setTimeout( () => {
			// 			data.dispatch( 'core/notices' ).removeNotice( res.notice.id );
			// 		}, 3000 );
			// 	} );
		} );
	}

	componentDidMount() {
		this.fetch();
	}

	notice( message, type = 'success' ) {
		data.dispatch( 'core/notices' )
			.createNotice( type, message, {
				type: 'snackbar',
			} )
			.then( ( res ) => {
				setTimeout( () => {
					data.dispatch( 'core/notices' ).removeNotice(
						res.notice.id
					);
				}, 10000 );
			} );
	}

	fetch() {
		this.setState(
			{
				loading: true,
			},
			() => {
				apiFetch( {
					path: 'hamail/v1/marketing/' + this.props.post_id,
				} )
					.then( ( res ) => {
						this.setState( {
							marketing: res,
						} );
					} )
					.catch( ( res ) => {
						this.notice( res.message, 'error' );
					} )
					.finally( () => {
						this.setState( {
							loading: false,
						} );
					} );
			}
		);
	}

	sync() {
		this.setState(
			{
				loading: true,
			},
			() => {
				apiFetch( {
					path: 'hamail/v1/marketing/' + this.props.post_id,
					method: 'post',
				} )
					.then( ( res ) => {
						this.setState( {
							marketing: res,
						} );
					} )
					.catch( ( res ) => {
						this.notice( res.message, 'error' );
					} )
					.finally( () => {
						this.setState( {
							loading: false,
						} );
					} );
			}
		);
	}

	unSync() {
		if (
			window.confirm(
				__(
					'This action removes syncing campaign draft from SendGrid. This is helpful for changing mail type(e.g. plain text to html). Are you sure?',
					'hamail'
				)
			)
		) {
			this.setState(
				{
					loading: true,
				},
				() => {
					apiFetch( {
						path: 'hamail/v1/marketing/' + this.props.post_id,
						method: 'delete',
					} )
						.then( ( res ) => {
							this.setState( {
								marketing: null,
							} );
							this.notice( res.message );
						} )
						.catch( ( res ) => {
							this.notice( res.message, 'error' );
						} )
						.finally( () => {
							this.setState( {
								loading: false,
							} );
						} );
				}
			);
		}
	}

	render() {
		const { loading, marketing } = this.state;
		const classNames = [ 'hamail-marketing-status' ];
		if ( loading ) {
			classNames.push( 'loading' );
		}
		return (
			<div className={ classNames.join( ' ' ) }>
				<h4>{ __( 'API Status', 'hamail' ) }</h4>
				{ marketing ? (
					<>
						<p>
							<span className="hamail-marketing-status text-sg-green">
								<span className="dashicons dashicons-yes"></span>{ ' ' }
								{ __( 'Syncing', 'hamail' ) }
							</span>
							{ __( 'ID:', 'hamail' ) }
							<code>{ marketing.id }</code>
							<span className="hamail-marketing-status">
								{ __( 'Status:', 'hamail' ) }{ ' ' }
								<strong>{ marketing.status }</strong>
							</span>
						</p>
						<p>
							<a
								className="button"
								href={ `https://sendgrid.com/marketing_campaigns/ui/campaigns/${ marketing.id }/edit` }
								target="_blank"
								rel="noreferrer noopener"
							>
								{ __( 'See in SendGrid', 'hamail' ) }
							</a>
							<Button
								variant="tertiary"
								onClick={ () => this.unSync() }
								className="text-sg-grid"
								isBusy={ loading }
							>
								{ __( 'Unsync', 'hamail' ) }
							</Button>
						</p>
					</>
				) : (
					<p className="description text-sg-red">
						{ __( 'This marketing is not syncing.', 'hamail' ) }
						<Button
							variant="tertiary"
							onClick={ () => this.sync() }
							isBusy={ loading }
						>
							{ __( 'Sync', 'hamail' ) }
						</Button>
					</p>
				) }
				{ loading && <Spinner></Spinner> }
			</div>
		);
	}
}

const wrapper = document.getElementById( 'hamail-marketing-info' );
if ( createRoot ) {
	// React >= 18
	const container = createRoot( wrapper );
	container.render(
		<MarketingStatus post_id={ wrapper.dataset.id }></MarketingStatus>
	);
} else {
	// React < 18
	render(
		<MarketingStatus post_id={ wrapper.dataset.id }></MarketingStatus>,
		wrapper
	);
}
