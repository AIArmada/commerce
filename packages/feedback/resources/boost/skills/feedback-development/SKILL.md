---
name: feedback-development
description: Develop and integrate feedback using its owned Dune features.
---

# Feedback development

Feedback forms, invitations, responses, testimonials and analytics are owned by separate features under `Features/`. Use the owning feature's Actions for form lifecycle, invitation expiration, response submission, testimonial transitions and analytics recalculation. Never bypass respondent identity checks or invitation state checks; keep anonymous drafts isolated.

Run the package architecture checks from the monorepo and the affected Pest regression tests before shipping changes. Do not introduce legacy aliases or duplicate write paths.
