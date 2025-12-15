# Multitenancy Guidelines
- **Pkg**: `commerce-support`.
- **Impl**:
- Mig: `$table->nullableMorphs('owner')`.
- Model: `use HasOwner`.
- Provider: Bind `OwnerResolverInterface`.
- **Usage**:
- Owner: `Model::forOwner($owner)->get()`.
- Global: `Model::globalOnly()->get()`.