# Architecture Design Overview - Ideally

This architecture is designed specifically to address the provided constraints, leveraging the strengths of a **WordPress** application running on **Amazon EKS**.

## Why This Architecture?
* **Scalability:** By utilizing Kubernetes’s horizontal scaling, the system can support 1.5M visitors. Within EKS, WordPress pods are configured to scale horizontally based on CPU/Memory thresholds—for example, if a marketing campaign brings 50k users in an hour, the cluster expands automatically.
* **Edge Layer:** I have assumed a **CloudFront Edge** setup. The goal is to serve 90% of the page’s assets (images, CSS, shell HTML) from the edge. 
* **Personalization at Scale:** Only the 10% of the page that is “personalized” triggers a request to the origin. All profiles (industry, past behavior, etc.) are stored in **Redis (ElastiCache)** to avoid slow MySQL joins and reduce Time-to-First-Byte (TTFB).



## Marketing Team Autonomy
This setup allows content editors to stay in a familiar environment (**WordPress**). 
* **PersonalizeWP & ACF:** By using custom blocks via Advanced Custom Fields (ACF) integration, we can set up a robust system for all personalization needs (rules, tagging, etc.). 
* **Visitor Insights:** Each visitor gets a profile that tracks site interactions, page visits, and form submissions directly within the system.

## Security and GDPR Compliance
Unlike third-party SaaS tools, this setup keeps all visitor data within a **private AWS VPC**. 
* **Data Protection:** No personally identifiable information (PII) leaves the infrastructure. Both the database (RDS) and Redis are encrypted. 
* **Encryption:** All traffic between the edge layer and compute layer is forced over HTTPS. 
* **Access Control:** The EKS cluster lives in a private subnet. The only entry point is through the Application Load Balancer (ALB), which sits behind an **AWS WAF** to block SQL injection and cross-site scripting (XSS) attempts.

---

## What I Would Add With More Time
* **Explore Edge Side Includes (ESI):** I would implement ESI to "stitch" personalized blocks into cached pages directly at the CDN level, removing the need for AJAX "content flickers."
* **Automated A/B Testing:** Integrate a framework to automatically test which personalization rules (e.g., "Finance" vs. "Enterprise") are actually driving more conversions.

## Known Limitations
* **The "Cold Start" Problem:** New visitors with no history receive a generic experience. Overcoming this requires expensive 3rd-party identity resolution tools (like 6Sense or Clearbit) to guess the industry based on IP address.
* **Cache Complexity:** While CloudFront is powerful, managing "cache invalidation" (clearing old versions when a rule changes) requires careful setup to ensure users don't see outdated content.
* **Plugin Dependency:** Using a specific plugin means we are tied to their update cycle. However, for a "small-to-moderate" budget, this is a calculated trade-off compared to the high cost of a custom-built engine.