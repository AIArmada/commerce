# Feedback

Feedback forms, invitations, responses, testimonials and analytics are owned by separate features under `Features/`. Use the owning feature's Actions for form lifecycle, invitation expiration, response submission, testimonial transitions and analytics recalculation. Never bypass respondent identity checks or invitation state checks; keep anonymous drafts isolated.

Testimonial extraction reads a locked, detached source from the Responses feature, accepts only submitted or reviewed responses, and never includes anonymous respondent identities.

Subject-level score and NPS reads use the Responses feature's ReadsSubjectFeedbackMetrics API and include submitted and reviewed responses only.

Create subject-bound forms through the Forms feature's CreatesFormsFromTemplates API. It accepts a template slug and explicit subject identity, rejects ambiguous slugs, and returns the new form ID rather than an ORM model.
