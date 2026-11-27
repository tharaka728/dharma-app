# Frozen synthetic snapshot evidence

These six JSON files are source-controlled test artifacts, not database fixtures or genuine invoices. Never regenerate them from the compatibility validator under test.

Reference: commit `ed147d41894b109fae277e8e69b40e8a3dfecb57`, original policy blob `cddfcd862909f096f06a26d7e80a626ce3d44916`. Historical outputs and unit runs were captured once by evaluating the original Git sources in an isolated reference namespace, without application bootstrap, PDO construction or fixture builders. Current examples were established from those synthetic inputs using explicitly specified component cents, not the current application evaluator: Glucose gross 800/P=50/I=0/net=750, and mixed Glucose 750 plus ordinary gross 500/I=50/net=450. Pinned original unit-allocation code supplied the fixed component runs. Review identity/date/customer metadata is synthetic and fixed.

Canonical unsigned snapshot digests were independently checked using a separate standalone JSON key-ordering routine and .NET SHA256. Tests hard-code these expected digests and independently assert monetary/unit/return results. Fixture files are only read during tests.

| Artifact | Expected unsigned canonical SHA256 | Interpretation |
| --- | --- | --- |
| `legacy-675.json` | `a856036ba1d9f8779683cd7589490e7cfbb91b713b8abb5a3cf9227d70a29e13` | Historical Glucose P=50/I=75/net=675; segment invoice allocations 60/15; remainder units 67.50, qualified units 90 |
| `current-unmarked-750.json` | `86fc9ff378fe444bac07a2a6b4e6df2600ade52ba9bc4ee51dec15304d6eb137` | Already-issued unmarked current Glucose, I=0/net=750 |
| `current-marked-750.json` | `35ed095555f928e791697ce660fa3d9e507a2055e673c2c56d3108d4c4567d7e` | Future current-only sealed review marker, same Glucose money |
| `mixed-marked-1200.json` | `e701bea743a5c98e5473c60e5af9720e84a4c144107b9f2672a739c5fd9f3eb7` | Eligible base 500, I=50, total 1200 |
| `equivalent-unmarked.json` | `bee0e70526c129dfd056d7e3abf36bb89eff661e6124e5e6f50a0d2b3e934ae7` | Ordinary 5 x 100, product 0%, invoice 10%, net 450; complete results match both rules |
| `legacy-replacement-810.json` | `bcbd759def8ed7c29c1bc428619cbd10eb988e32f1e48f0fa83eaa42f1b2b04d` | Gross 1200/R=300/I=90/net=810; two-unit credit 135 |

An invoice rate of zero on a promoted line does not necessarily mean equivalent outputs: the old and new stored discount bases can differ even with identical payable amounts. Only complete calculation and allocation matches establish compatibility.

`sources.json` records original blob IDs and LF-normalized SHA256 hashes of the frozen implementation. Whole pure policy/arithmetic/unit classes are namespaced copies. `BenefitPolicy` retains only constants and pure validation/application/eligibility/revision methods; operational gates are excluded. `Products` and `Inventory` retain only the original money/quantity validators. `HybridInvoiceRequest` retains only original canonical JSON/digest methods. `MixedOil` retains only the original constant (mixed rules are rejected by the policy). No configuration, clocks, persistence, network or operational feature gates are copied. Imports resolve arithmetic to frozen helpers; support exceptions remain shared.

The historical evaluator is for sealed-read verification only. It is not exposed by request normalization, checkout preview, issue or new persistence. Future edits to pinned logic require explicit compatibility review and independent new evidence; do not update hashes to hide changed behavior. Self-hashes are integrity checks, not digital signatures or proof against replacement of an entire trusted store.
