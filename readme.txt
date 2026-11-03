=== Dernek Yazılımı ===
Contributors: lkdtr
Tags: donation, membership, volunteer, association, nonprofit
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.12
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Donation, volunteer and membership forms for associations that run the open source Dernek Yazılımı portal.

== Description ==

[Dernek Yazılımı](https://github.com/lkdtr/dernekyazilimi) is an open source portal for associations (members, dues, donations, volunteers). This plugin puts the portal's public forms on your WordPress site:

* **Donation** – the donor fills in the form on your site; the card payment page of your payment provider, or the bank accounts for a transfer, follow in a frame.
* **Volunteer registration** – name, email and a mobile phone verified by SMS. The person sets a password from the email the portal sends; no password is typed on your site.
* **Document verification** – look up a letter by its verification code, including automatic lookup from a QR link; shows its number, date, subject and cancellation status.
* **Membership application** – the first step opens the portal account on your site; the application itself continues in a frame of the portal.

The forms are plain HTML styled by your theme (fonts, colours, buttons), so they look like the rest of your site. Framed portal pages have no header or menu, a transparent background and resize themselves to their content.

Add a form with its block (search for "Dernek Yazılımı") or its shortcode:

* `[dernekyazilimi_donate]` – optional attributes: `cause="3"` (preselected donation cause), `amount="250"`
* `[dernekyazilimi_volunteer]`
* `[dernekyazilimi_verify]` – document verification; QR links with `?kod=...` are looked up automatically
* `[dernekyazilimi_membership]`

Two more shortcodes keep texts and logos in one place, the portal:

* `[dernekyazilimi_agreement key="payment-terms"]` – the text of an agreement published in the portal, e.g. the payment, cancellation and refund terms, on a page of your site. `title="1"` adds the agreement's title as a heading.
* `[dernekyazilimi_payment_logos]` – the logos your card payment provider asks you to show (for example in the footer).

= Requirements =

You need your own Dernek Yazılımı portal, served over HTTPS. In the portal's admin panel, under Settings → Organization settings:

1. add the address of your WordPress site to the sites allowed to embed pages,
2. make an API key under "Web site connection".

Then enter the portal address and the key under Settings → Dernek Yazılımı in WordPress.

== External services ==

Document verification sends the entered document code to your own portal through the plugin's server-side API connection. Document metadata and public file links are returned to visitors who know the verification code. The letter and attachments are available through verification-code links. Create a page containing `[dernekyazilimi_verify]` and set its HTTPS address in the portal's correspondence settings to use it in new QR links.


This plugin connects to the Dernek Yazılımı portal of your own association, at the address you enter in the plugin's settings. It is not a third-party service run by the plugin's authors: the portal is software your association hosts, and your association's own terms and privacy policy apply (the forms link to the privacy policy published in the portal).

What is sent, from your site's server to the portal, and when:

* When a page with a form is shown (cached for five minutes): a request for the forms' configuration. It carries the API key and your site's address.
* When a visitor asks for a verification code or enters it: the phone number and the code.
* When a visitor submits the volunteer or membership form: first name, last name, email, phone number, communication permissions and the acceptance of the privacy policy.
* When a visitor submits the donation form: amount, donation cause, name, email, phone, message, the choice to hide the name, payment method, the acceptance of the privacy policy and of the payment terms, and the answer of the Cloudflare Turnstile check when it is on.
* When a page with the agreement shortcode is shown (cached for five minutes): a request for the agreement's text.

When the portal has Cloudflare Turnstile turned on, the donation form shows Cloudflare's human check: the visitor's browser loads its script from challenges.cloudflare.com, which receives the visitor's IP address and browser details to decide whether the visitor is a person. The answer is sent with the donation to the portal, which checks it with Cloudflare. Cloudflare's terms: https://www.cloudflare.com/website-terms/ – privacy policy: https://www.cloudflare.com/privacypolicy/

The logos of the card payment provider are images served by your portal; the visitor's browser loads them from the portal's address.

With each of these the visitor's IP address and browser name are passed on, so that the portal can limit abuse and record who accepted the privacy policy.

After a form is submitted, the visitor's browser loads a page of the portal in a frame. For a card donation that frame shows the payment page of the payment provider configured in your portal (for example iyzico – [terms](https://www.iyzico.com/en/legal/terms-of-use), [privacy policy](https://www.iyzico.com/en/privacy-policy)); card details are entered there and never reach your WordPress site.

Source of the plugin: https://github.com/lkdtr/dernekyazilimi-wordpress – source of the portal: https://github.com/lkdtr/dernekyazilimi

== Installation ==

1. Install and activate the plugin.
2. In your portal, allow your site and make an API key (see Requirements).
3. Go to Settings → Dernek Yazılımı, enter the portal address and the API key and save. "Connection status" shows which forms are open.
4. Add a form block or shortcode to a page.

The address and the key can also be set in `wp-config.php` with the constants `DERNEKYAZILIMI_PORTAL_URL` and `DERNEKYAZILIMI_API_KEY`.

== Frequently Asked Questions ==

= A form does not appear on the page =

Visitors see nothing when the portal cannot be reached or the module is off; administrators see the reason in place of the form. Check Settings → Dernek Yazılımı → Connection status.

= The portal answers that the site is not allowed =

The address of your WordPress site (with https) must be listed in the portal's organization settings under the sites allowed to embed pages.

= Does the frame work when third-party cookies are blocked? =

Yes. The portal keeps the frame's session in a partitioned cookie, and the donation pages need no session at all. Under every frame there is also a link to open the page in a new tab.

= Are card details processed by my site? =

No. They are entered on the payment provider's page shown in the frame.

== Changelog ==

= 1.0.12 =
* Document verification asks for the letter's number with the code; QR links still need the code only.

= 1.0.11 =
* Document verification has a new layout with a green shield, PDF open and download links.
* Document verification uses the same human check as the donation form when the portal has it on.

= 1.0.10 =
* Document verification shows the letter, PDF attachments and original file downloads.

= 1.0.9 =
* Document verification block and shortcode; automatic lookup from QR links.
* Shows public document details and cancellation status without sharing document content.


= 1.0.8 =
* Frames show "Loading…" until the page in them is in, so a slow payment page no longer looks like an empty window.

= 1.0.7 =
* Donation form shows a Cloudflare Turnstile check when the portal has Turnstile keys, and sends its answer to the portal with the donation. This stops stolen cards from being tried through the form.

= 1.0.6 =
* Dialogs keep their size in themes that resize frames or use a small root font size (e.g. Twenty Twenty).

= 1.0.5 =
* Donation form asks for the payment, cancellation and refund terms published in the portal and shows the logos of the card payment provider.
* New shortcodes: `[dernekyazilimi_agreement]` shows an agreement text kept in the portal, `[dernekyazilimi_payment_logos]` shows the payment provider logos (e.g. in the footer).

= 1.0.4 =
* The agreement text opens in a dialog over the page.

= 1.0.3 =
* Fixed donation amounts look like the amount buttons.

= 1.0.2 =
* Donation form offers only the listed amounts when the portal allows no free amount.

= 1.0.1 =
* Card payment opens in a dialog over the page; the result stays on the page after the payment.

= 1.0.0 =
* First release: donation, volunteer registration and membership application forms as blocks and shortcodes.
