# Feedback

Feedback forms, invitations, responses, testimonials and analytics are owned by separate features under `Features/`. Use the owning feature's Actions for form lifecycle, invitation expiration, response submission, testimonial transitions and analytics recalculation. Never bypass respondent identity checks or invitation state checks; keep anonymous drafts isolated.

Testimonial extraction reads a locked, detached source from the Responses feature, accepts only submitted or reviewed responses, and never includes anonymous respondent identities.
