# Owner-only mobile pilot — account/licensing checkpoint

9 November 2026.

Owner approval received for Tailscale installation, private owner-only connectivity, Serve HTTPS, review of installer network changes, and a separate loopback gateway on 8090. Public Funnel, purchases, paid trials, broad access and company rollout remain unauthorized. This supersedes the general network-approval checkpoint in mobile-access-plan.md; approval is not being requested again.

## Actual results this turn

- Existing access plan and gateway preparation script inspected. Proposed gateway is separate from existing 8088, serves only public/, and retains private MariaDB 3309. No prepared gateway was activated.
- No Tailscale executable found on PATH or in the standard Program Files locations; no Tailscale service found. Windows winget is available. No locally available Tailscale account/plan/administrator state can be verified.
- Official current pricing states Personal is only suitable for non-commercial use. This is a company application pilot, so Personal suitability is not established and will not be assumed merely because only the owner tests it or uses a personal email.
- Standard is listed at USD 8 per user/month. Custom-domain business signup automatically enrolls in a free trial; the published evaluation offer is fourteen days. No signup, trial, subscription or purchase was started.
- No installation, account sign-in, adapter/firewall change, Serve/Funnel configuration, gateway start, database change or phone interaction performed. No actual HTTPS mobile URL exists. HTTPS/session/phone tests remain NOT TESTED. Earlier application tests are historical evidence in the linked plan, not newly rerun tests this turn.

Sources checked: [official pricing](https://tailscale.com/pricing), [trial offer terms](https://tailscale.com/free-trial-offer-terms). Owner/Admin access to the intended tailnet is required to review members/devices, grants, HTTPS settings and absence of public Funnel before activation.

## Blocking prerequisite

The owner's mandatory execution order requires verifying the actual plan before installation/use, and stopping on licensing blockers. An eligible business account and authority to configure it have not been established. Do not log in with a personal email as a licensing workaround, or click through signup/trial/billing screens without reviewing them.

To resume, identify either:

1. An existing appropriately licensed business tailnet, with owner/admin access available for interactive sign-in and plan verification; or
2. Explicit approval to use the published fourteen-day **free business evaluation**, subject to confirming the actual signup has no charge, payment obligation or automatic paid conversion. Do not enter payment details or buy a plan. If signup terms differ, stop; or
3. Vendor permission establishing that this specific company pilot is eligible for another no-cost arrangement.

Do not send passwords, auth keys, recovery codes or payment information in chat. Only account/plan type and availability of owner/admin access are needed initially. Interactive Windows/browser and phone sign-in will be performed by the owner; no account is impersonated.

After the prerequisite is resolved: review the official signed installer, capture Windows network/firewall state, install, let the owner authenticate both devices, verify actual plan and owner-only policy, prepare the private gateway, enable Serve only, verify HTTPS/cookies/private-path denial, then record the real URL and test phone installation. All previously approved work remains authorized; no repeat network approval is required.

The existing [mobile access plan](mobile-access-plan.md) contains the reviewed configuration, installation and rollback commands. Neither gateway nor Serve was enabled, so no network rollback is currently needed.
