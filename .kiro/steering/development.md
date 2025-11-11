# Development Guidelines

## Core Principles

### Keep It Simple
- **KISS Principle**: Keep It Simple, Stupid - always choose the simplest solution that works
- **Avoid Over-Engineering**: Don't build complex solutions for simple problems
- **Minimal Code**: Write only the code that's absolutely necessary
- **Clear Intent**: Code should be obvious and easy to understand

### Problem-Solving Approach
- **Start Simple**: Begin with the most straightforward solution
- **Iterate Only When Needed**: Add complexity only when the simple solution proves insufficient
- **One Problem at a Time**: Focus on solving the immediate issue, not potential future problems
- **Prefer Readable Over Clever**: Choose clear, obvious code over clever optimizations

### Code Quality
- **Readability First**: Code is read more often than it's written
- **Consistent Style**: Follow existing patterns in the codebase
- **Meaningful Names**: Use descriptive variable and function names
- **Small Functions**: Keep functions focused on a single responsibility

### When to Add Complexity
Only add complexity when:
- The simple solution doesn't work
- Performance requirements demand it
- Security considerations require it
- The business logic is inherently complex

### Red Flags
Avoid these patterns:
- Building frameworks within the application
- Premature optimization
- Abstract solutions for concrete problems
- Complex inheritance hierarchies
- Over-abstraction of simple operations