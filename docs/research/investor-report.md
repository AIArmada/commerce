---
title: Investor Report (Non-Technical)
status: research
---

# Investor report: the affiliate business in plain English

## What this is

Every online store wants more salespeople but doesn't want to pay salaries.
Affiliate marketing solves that: ordinary people, creators, and bloggers
promote a store's products, and only get paid when their promotion actually
produces a sale. No sale, no cost to the store.

We have built the complete software to run this model — both sides of it:

1. **A program-in-a-box for a single store** (the engine, `aiarmada/affiliates`).
   Any merchant can recruit their own promoters, hand them tracking links,
   and pay them automatically when sales come in.
2. **A marketplace that connects many stores with many promoters** (the network,
   `aiarmada/affiliate-network`). Stores list their offers; creators browse,
   apply, and promote products from stores they don't own. This is the bigger
   play — it becomes the mall where all this commerce happens.

Either side works on its own, and they plug together cleanly when a merchant
wants both.

## The money flow (the most important part)

Follow one dollar:

1. A creator shares a special link to a store's product.
2. A shopper clicks it and buys something for $100.
3. The store owes the creator a commission — say 15%, or $15.
4. That $15 sits in a holding area for 30 days (so refunds and fraud can be
   caught), then becomes withdrawable.
5. The creator cashes out to their bank, PayPal, or Stripe once their balance
   passes a minimum (about $50 equivalent).

Money always flows from the store to the promoter. Our software tracks every
cent of that obligation, holds it safely through the risk window, moves it on
payout, and keeps records that prove every number is correct.

**The honest caveat:** today the store pays the promoter in full — our software
does not take a cut. The marketplace's own revenue layer (a percentage of each
sale, listing fees, or similar) is not built yet. That is the main thing
standing between this technology and a business model, and it needs a
deliberate decision.

## What the store product does

Think of it as hiring a tireless, perfectly honest sales commission department:

- **Recruiting.** Anyone can sign up to promote the store; the store can
  auto-accept them or approve each one by hand. Promoters can even recruit
  sub-promoters beneath them (two levels, with a share of commission flowing
  upward).
- **Tracking.** Every promoter gets personal links. When shoppers click, the
  software remembers who sent them for 30 days — through browser cookies,
  discount codes, or device fingerprints. It blocks cheats: fake clicks,
  self-purchases, and bot traffic are detected and thrown out.
- **Flexible commissions.** The store can pay a percentage, a flat amount, or
  build sophisticated rules — different rates per product, bonuses for top
  sellers, limited-time promotions, higher rates for high-volume partners. It
  handles multiple currencies properly.
- **The waiting room.** Earnings sit in "holding" for 30 days so refunds
  settle, then move to "available." If a sale is cancelled, the commission is
  clawed back automatically.
- **Payday.** Promoters save a bank account, PayPal, or Stripe destination.
  The store pays them out in batches, with records for every transfer, the
  ability to freeze suspicious accounts, and reconciliation reports that prove
  the books balance.
- **Keeping score.** Daily dashboards: clicks, sales, conversion rates,
  earnings per click, top performers, trends. Promoters can see their own
  numbers.
- **The relationship extras.** Ranks and badges, training courses, a support
  desk, tax documents, and automatic notifications to outside systems when
  sales happen. This is a mature partner-management product, not a bare
  tracker.

## What the marketplace product does

This is where it gets interesting as a business. Instead of each store
recruiting alone, the marketplace aggregates everything:

**For stores:** register the shop, prove they own it (a few standard
verification methods), and either list offers by hand or — with one click —
automatically mirror their entire product catalog from the store software into
the marketplace. They review and approve the creators who want to promote them.

**For creators:** browse all offers across all stores in one place, apply with
one click (many offers approve instantly), get a tracking link, and share it.
Every click and sale is counted live on their dashboard.

**Behind the scenes:** every click passes a policy check (is the offer still
live? the store still verified? this creator still approved?), bots are
filtered out, and sales are reported back either automatically from connected
checkouts or through a standard sales-report connection remote stores plug
into. A reconciliation system continuously proves the marketplace's count of
sales matches the money actually booked — the kind of control auditors love.

## Why the design is strong

Three things a non-technical investor should still appreciate, because they
reduce risk:

1. **The two halves are genuinely independent.** The marketplace runs fine
   without the store software (this is how our own network property operates
   today), and a store can run its program with no marketplace. Nothing is
   tangled. That means each product can be sold, priced, and developed
   separately.
2. **The numbers are built to survive scrutiny.** Commissions are stored as
   whole cents (never rounded decimals), every sale is recorded exactly once
   even if reported twice, different currencies are never mixed together, and
   there are reconciliation checks at every level. Financial software lives or
   dies on this discipline, and it's here.
3. **Fraud was designed in, not bolted on.** Velocity checks, duplicate
   detection, self-dealing blocks, and payout freezes are part of the core
   flow, with a holding period that makes most scams unprofitable.

## What still needs attention

Candor section — four items:

1. **The marketplace doesn't charge anyone yet.** This is the business-model
   gap described above. The plumbing for it doesn't exist; it must be scoped
   and built.
2. **A rare double-payment scenario.** If a single purchase is somehow credited
   through both a store's own program and the marketplace at once, both could
   pay out today. It's an edge case, but it needs an explicit rule (one side
   wins, or they split).
3. **Remote sales are taken on trust.** When a far-away store reports "we made
   a $100 sale," the marketplace believes the amount. Access is authenticated
   and stores are verified, but the figure itself isn't independently checked.
   That's a normal industry trust boundary — it just needs to be a conscious
   one.
4. **A few rough edges.** Some notification hooks exist but nothing listens to
   them yet; volume bonuses show on marketplace listings but only the base
   rate is actually paid through that path. Small finish-work items, not
   structural problems.

## Bottom line

The technology is the real thing: a complete store-level affiliate program
with best-practice financial controls, plus a multi-store marketplace with a
clean connection between the two. The engineering risk is low. What remains is
commercial — deciding how the network earns its keep — plus a short list of
finish-work and one policy decision on dual attribution.

## Sources

Primary-source technical companion with per-claim file citations:
`docs/research/affiliate-network-study.md`.
